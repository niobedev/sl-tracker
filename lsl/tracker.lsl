// Avatar Tracking System - LSL Script (FIXED)
// Tracks avatar login/logout events using llRequestAgentData(DATA_ONLINE).
//
// IMPORTANT LIMITATION:
// llRequestAgentData(key, DATA_ONLINE) only returns meaningful results for:
//   - Avatars who are friends of the script owner AND have granted
//     "see my online status" permission, OR
//   - Avatars in a region where the owner is estate manager/owner.
// For arbitrary avatars it will typically return "0" regardless of real status.
// If this system appears to never detect logins, this is almost certainly why.

// ========== CONFIGURATION ==========
string API_URL = "https://your-domain.com";
string API_KEY = "your-api-key-here";
float CONFIG_POLL_INTERVAL = 60.0;   // How often to fetch config (seconds)
float AVATAR_CHECK_INTERVAL = 60.0;  // How often to check avatar status (seconds)
integer DEBUG = TRUE;

// DATA_ONLINE throttle: ~100 requests / 20 seconds per script. To stay safe
// we batch and spread queries. This is the max we will fire per poll tick.
integer MAX_QUERIES_PER_POLL = 20;

// ========== STATE ==========
list tracked_avatars = [];   // strided: [uuid, onlineStatus, displayName, pendingOnlineQuery]
                             // onlineStatus: -1 unknown, 0 offline, 1 online
integer STRIDE = 4;

// Pending DATA_NAME queries, strided: [queryId, uuid, action]
list pending_name_queries = [];
integer NAME_STRIDE = 3;

integer config_version = -1;
float last_config_check = -9999.0;   // Force immediate fetch on first tick
float last_avatar_check = -9999.0;   // Force immediate poll on first tick

// Round-robin index into tracked_avatars so we don't blow the DATA_ONLINE
// throttle when tracking many avatars.
integer poll_cursor = 0;

// ========== HTTP HELPERS ==========

key httpGet(string url) {
    if (DEBUG) llOwnerSay("DEBUG: GET " + url);
    return llHTTPRequest(
        url,
        [
            HTTP_METHOD, "GET",
            HTTP_CUSTOM_HEADER, "X-API-Key", API_KEY,
            HTTP_VERIFY_CERT, TRUE
        ],
        ""
    );
}

key httpPost(string url, string body) {
    if (DEBUG) llOwnerSay("DEBUG: POST " + url + " body=" + llGetSubString(body, 0, 100));
    return llHTTPRequest(
        url,
        [
            HTTP_METHOD, "POST",
            HTTP_MIMETYPE, "application/json",
            HTTP_CUSTOM_HEADER, "X-API-Key", API_KEY,
            HTTP_VERIFY_CERT, TRUE
        ],
        body
    );
}

// ========== CONFIG MANAGEMENT ==========

fetchConfig() {
    httpGet(API_URL + "/api/tracking-config");
}

processConfig(string body) {
    // Expected: {"trackedAvatars":["uuid1","uuid2"],"version":123,"pollInterval":60}

    // --- Extract trackedAvatars array ---
    string needle = "\"trackedAvatars\":[";
    integer needleLen = llStringLength(needle);  // 18, not 17
    integer start = llSubStringIndex(body, needle);
    if (start == -1) {
        if (DEBUG) llOwnerSay("DEBUG: No trackedAvatars in config");
        return;
    }
    start = start + needleLen;

    integer end = llSubStringIndex(llGetSubString(body, start, -1), "]");
    if (end == -1) {
        if (DEBUG) llOwnerSay("DEBUG: Malformed trackedAvatars array");
        return;
    }

    string arrayStr = llGetSubString(body, start, start + end - 1);

    // --- Parse UUIDs ---
    list newAvatars = [];
    integer i = 0;
    integer len = llStringLength(arrayStr);

    while (i < len) {
        integer quote1 = llSubStringIndex(llGetSubString(arrayStr, i, -1), "\"");
        if (quote1 == -1) i = len; // break
        else {
            integer subStart = i + quote1 + 1;
            integer quote2 = llSubStringIndex(llGetSubString(arrayStr, subStart, -1), "\"");
            if (quote2 == -1) i = len; // break
            else {
                string uuid = llGetSubString(arrayStr, subStart, subStart + quote2 - 1);
                uuid = llToLower(llStringTrim(uuid, STRING_TRIM));
                if (llStringLength(uuid) == 36) newAvatars += [uuid];
                i = subStart + quote2 + 1;
            }
        }
    }

    // --- Extract version ---
    needle = "\"version\":";
    needleLen = llStringLength(needle);  // 10
    start = llSubStringIndex(body, needle);
    if (start == -1) {
        if (DEBUG) llOwnerSay("DEBUG: No version field; applying avatar list anyway");
        updateAvatarList(newAvatars);
        return;
    }
    start += needleLen;

    integer comma = llSubStringIndex(llGetSubString(body, start, -1), ",");
    integer closeBrace = llSubStringIndex(llGetSubString(body, start, -1), "}");
    integer endVer = -1;
    if (comma != -1 && (comma < closeBrace || closeBrace == -1)) endVer = comma;
    else if (closeBrace != -1) endVer = closeBrace;
    if (endVer == -1) {
        if (DEBUG) llOwnerSay("DEBUG: Can't locate end of version field");
        return;
    }

    string verStr = llStringTrim(llGetSubString(body, start, start + endVer - 1), STRING_TRIM);
    integer newVersion = (integer)verStr;

    if (newVersion != config_version) {
        if (DEBUG) llOwnerSay("DEBUG: Config version " + (string)config_version + " -> " + (string)newVersion);
        updateAvatarList(newAvatars);
        config_version = newVersion;
    } else {
        if (DEBUG) llOwnerSay("DEBUG: Config version unchanged: " + (string)config_version);
    }
}

// Find the strided index of a uuid in tracked_avatars, or -1.
integer findAvatarIndex(string uuid) {
    integer n = llGetListLength(tracked_avatars);
    integer i;
    for (i = 0; i < n; i += STRIDE) {
        if (llList2String(tracked_avatars, i) == uuid) return i;
    }
    return -1;
}

updateAvatarList(list newAvatars) {
    // Remove avatars no longer in the new list (iterate backwards)
    integer i;
    for (i = llGetListLength(tracked_avatars) - STRIDE; i >= 0; i -= STRIDE) {
        string uuid = llList2String(tracked_avatars, i);
        if (llListFindList(newAvatars, [uuid]) == -1) {
            if (DEBUG) llOwnerSay("DEBUG: Removing avatar: " + uuid);
            tracked_avatars = llDeleteSubList(tracked_avatars, i, i + STRIDE - 1);
        }
    }

    // Add new avatars
    integer count = llGetListLength(newAvatars);
    for (i = 0; i < count; ++i) {
        string uuid = llList2String(newAvatars, i);
        if (findAvatarIndex(uuid) == -1) {
            if (DEBUG) llOwnerSay("DEBUG: Adding avatar: " + uuid);
            // [uuid, onlineStatus=-1, displayName=uuid, pendingOnlineQuery=NULL_KEY]
            tracked_avatars += [uuid, -1, uuid, NULL_KEY];
        }
    }

    if (DEBUG) llOwnerSay("DEBUG: Now tracking " +
        (string)(llGetListLength(tracked_avatars) / STRIDE) + " avatars");
    updateHoverText();
}

// ========== AVATAR TRACKING ==========

pollAvatars() {
    integer n = llGetListLength(tracked_avatars);
    if (n == 0) return;

    integer totalAvatars = n / STRIDE;
    integer maxThisTick = MAX_QUERIES_PER_POLL;
    if (maxThisTick > totalAvatars) maxThisTick = totalAvatars;

    integer fired;
    for (fired = 0; fired < maxThisTick; ++fired) {
        // Wrap cursor
        if (poll_cursor >= totalAvatars) poll_cursor = 0;
        integer idx = poll_cursor * STRIDE;
        poll_cursor++;

        string uuid = llList2String(tracked_avatars, idx);
        key k = (key)uuid;
        if (k == NULL_KEY) {
            if (DEBUG) llOwnerSay("DEBUG: Invalid UUID at index " + (string)idx + ": " + uuid);
        } else {
            key q = llRequestAgentData(k, DATA_ONLINE);
            tracked_avatars = llListReplaceList(tracked_avatars, [q], idx + 3, idx + 3);
        }
    }
}

processAvatarStatus(integer idx, integer isOnline) {
    integer wasOnline = llList2Integer(tracked_avatars, idx + 1);
    string uuid = llList2String(tracked_avatars, idx);

    // Clear pending query slot
    tracked_avatars = llListReplaceList(tracked_avatars, [NULL_KEY], idx + 3, idx + 3);

    if (wasOnline == -1) {
        tracked_avatars = llListReplaceList(tracked_avatars, [isOnline], idx + 1, idx + 1);
        if (DEBUG) llOwnerSay("DEBUG: Initial status for " + uuid + ": " + (string)isOnline);
        updateHoverText();
        return;
    }

    if (wasOnline == isOnline) return;

    // Status changed
    string action;
    if (isOnline) action = "login";
    else action = "logout";
    if (DEBUG) llOwnerSay("DEBUG: " + action + " detected for " + uuid);

    // Request name, then send event once we have it
    key nameKey = llRequestAgentData((key)uuid, DATA_NAME);
    pending_name_queries += [nameKey, uuid, action];

    tracked_avatars = llListReplaceList(tracked_avatars, [isOnline], idx + 1, idx + 1);
    updateHoverText();
}

sendEvent(string uuid, string action, string displayName) {
    string isoTime = llGetTimestamp();  // already ISO 8601 UTC

    // Escape any double quotes in displayName defensively
    string safeName = escapeJson(displayName);

    string json = "[{\"event_ts\":\"" + isoTime + "\","
                + "\"action\":\"" + action + "\","
                + "\"avatarKey\":\"" + uuid + "\","
                + "\"displayName\":\"" + safeName + "\","
                + "\"regionName\":\"global\"}]";

    httpPost(API_URL + "/api/events", json);
}

string escapeJson(string s) {
    // Minimal JSON string escaping
    string out = "";
    integer i;
    integer n = llStringLength(s);
    for (i = 0; i < n; ++i) {
        string c = llGetSubString(s, i, i);
        if (c == "\\") out += "\\\\";
        else if (c == "\"") out += "\\\"";
        else if (c == "\n") out += "\\n";
        else if (c == "\r") out += "\\r";
        else if (c == "\t") out += "\\t";
        else out += c;
    }
    return out;
}

updateHoverText() {
    integer n = llGetListLength(tracked_avatars) / STRIDE;
    if (n == 0) {
        llSetText("No avatars being tracked", <1,1,1>, 1.0);
        return;
    }

    integer onlineCount = 0;
    integer i;
    for (i = 0; i < n; ++i) {
        if (llList2Integer(tracked_avatars, i * STRIDE + 1) == 1) onlineCount++;
    }

    string text = "Tracking " + (string)n + " avatars\n"
                + "Online: " + (string)onlineCount
                + " / Offline: " + (string)(n - onlineCount);
    llSetText(text, <1,1,1>, 1.0);
}

// Find strided index of a pending online query by its queryId, or -1.
integer findPendingOnlineQuery(key qid) {
    integer n = llGetListLength(tracked_avatars);
    integer i;
    for (i = 0; i < n; i += STRIDE) {
        if ((key)llList2String(tracked_avatars, i + 3) == qid) return i;
    }
    return -1;
}

// Find index of pending name query (strided), or -1.
integer findPendingNameQuery(key qid) {
    integer n = llGetListLength(pending_name_queries);
    integer i;
    for (i = 0; i < n; i += NAME_STRIDE) {
        if ((key)llList2String(pending_name_queries, i) == qid) return i;
    }
    return -1;
}

// ========== MAIN ==========

default {
    state_entry() {
        llOwnerSay("Avatar Tracking System starting...");
        llOwnerSay("API URL: " + API_URL);

        if (API_KEY == "your-api-key-here") {
            llOwnerSay("ERROR: Please configure API_KEY in the script!");
        }
        if (llSubStringIndex(API_URL, "https://") != 0) {
            llOwnerSay("WARNING: API_URL is not https://; llHTTPRequest may fail for some endpoints.");
        }

        llSetTimerEvent(5.0); // tick every 5s; actual work gated by intervals
        updateHoverText();
    }

    on_rez(integer start_param) {
        llResetScript();
    }

    changed(integer change) {
        if (change & CHANGED_OWNER) llResetScript();
    }

    timer() {
        float now = llGetTime();

        if (now - last_config_check >= CONFIG_POLL_INTERVAL) {
            fetchConfig();
            last_config_check = now;
        }

        if (now - last_avatar_check >= AVATAR_CHECK_INTERVAL) {
            pollAvatars();
            last_avatar_check = now;
        }
    }

    http_response(key request_id, integer status, list metadata, string body) {
        if (status != 200) {
            if (DEBUG) llOwnerSay("DEBUG: HTTP error " + (string)status
                + ": " + llGetSubString(body, 0, 200));
            return;
        }

        if (llSubStringIndex(body, "\"trackedAvatars\"") != -1) {
            processConfig(body);
            return;
        }

        if (llSubStringIndex(body, "\"received\"") != -1) {
            if (DEBUG) llOwnerSay("DEBUG: Events received by server");
            return;
        }

        if (DEBUG) llOwnerSay("DEBUG: Unhandled HTTP response: "
            + llGetSubString(body, 0, 100));
    }

    dataserver(key query_id, string data) {
        // Check name queries FIRST — they're fewer and more specific.
        integer nameIdx = findPendingNameQuery(query_id);
        if (nameIdx != -1) {
            string uuid = llList2String(pending_name_queries, nameIdx + 1);
            string action = llList2String(pending_name_queries, nameIdx + 2);

            // DATA_NAME returns legacy username like "firstname.lastname" or
            // "Firstname Lastname". It is NOT the display name — LSL has no
            // direct way to fetch display name. Backend should resolve it if
            // needed.
            list nameParts = llParseString2List(data, [" "], []);
            string displayName = llList2String(nameParts, 0);
            if (displayName == "") displayName = data;

            sendEvent(uuid, action, displayName);

            pending_name_queries = llDeleteSubList(
                pending_name_queries, nameIdx, nameIdx + NAME_STRIDE - 1);
            return;
        }

        // Otherwise, treat as online-status query.
        integer idx = findPendingOnlineQuery(query_id);
        if (idx != -1) {
            integer isOnline = (data == "1");
            processAvatarStatus(idx, isOnline);
            return;
        }

        if (DEBUG) llOwnerSay("DEBUG: Unmatched dataserver query: "
            + (string)query_id + " data=" + data);
    }
}
