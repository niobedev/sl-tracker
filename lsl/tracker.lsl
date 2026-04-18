// Avatar Tracking System - LSL Script (FIXED v2)
// Tracks avatar login/logout events using llRequestAgentData(DATA_ONLINE).
//
// IMPORTANT LIMITATION:
// llRequestAgentData(key, DATA_ONLINE) only returns meaningful results for:
//   - Avatars who are friends of the script owner AND have granted
//     "see my online status" permission, OR
//   - Avatars in a region where the owner is estate manager/owner.
// For arbitrary avatars it typically returns "0" regardless of real status.

// ========== CONFIGURATION ==========
string API_URL = "https://your-domain.com";
string API_KEY = "your-api-key-here";
float CONFIG_POLL_INTERVAL = 60.0;
float AVATAR_CHECK_INTERVAL = 60.0;
integer DEBUG = TRUE;

// If TRUE, a login event is emitted the first time we observe an avatar as
// online (i.e. on the -1 -> 1 transition at startup). Useful for testing and
// also often what you actually want so you don't miss people already online
// when the script was reset.
integer EMIT_INITIAL_ONLINE = FALSE;

// DATA_ONLINE throttle: ~100 requests / 20 seconds per script.
integer MAX_QUERIES_PER_POLL = 20;

// ========== STATE ==========
list tracked_avatars = [];   // strided: [uuid, onlineStatus, displayName, pendingOnlineQuery]
integer STRIDE = 4;

integer config_version = -1;
float last_config_check = -9999.0;
float last_avatar_check = -9999.0;
integer poll_cursor = 0;

// ========== DEBUG ==========

debugLog(string msg) {
    if (!DEBUG) return;
    integer len = llStringLength(msg);
    integer chunkLen = 250;
    integer i;
    for (i = 0; i < len; i += chunkLen) {
        integer end = i + chunkLen;
        if (end > len) end = len;
        llOwnerSay("DEBUG: " + llGetSubString(msg, i, end - 1));
    }
}

// ========== HTTP HELPERS ==========

key httpGet(string url) {
    debugLog("GET " + url);
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
    debugLog("POST " + url + " body=" + body);
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
    string needle = "\"trackedAvatars\":[";
    integer needleLen = llStringLength(needle);
    integer start = llSubStringIndex(body, needle);
    if (start == -1) {
        debugLog("No trackedAvatars in config");
        return;
    }
    start = start + needleLen;

    integer end = llSubStringIndex(llGetSubString(body, start, -1), "]");
    if (end == -1) {
        debugLog("Malformed trackedAvatars array");
        return;
    }

    string arrayStr = llGetSubString(body, start, start + end - 1);

    list newAvatars = [];
    integer i = 0;
    integer len = llStringLength(arrayStr);

    while (i < len) {
        integer quote1 = llSubStringIndex(llGetSubString(arrayStr, i, -1), "\"");
        if (quote1 == -1) i = len;
        else {
            integer subStart = i + quote1 + 1;
            integer quote2 = llSubStringIndex(llGetSubString(arrayStr, subStart, -1), "\"");
            if (quote2 == -1) i = len;
            else {
                string uuid = llGetSubString(arrayStr, subStart, subStart + quote2 - 1);
                uuid = llToLower(llStringTrim(uuid, STRING_TRIM));
                if (llStringLength(uuid) == 36) newAvatars += [uuid];
                i = subStart + quote2 + 1;
            }
        }
    }

    needle = "\"version\":";
    needleLen = llStringLength(needle);
    start = llSubStringIndex(body, needle);
    if (start == -1) {
        debugLog("No version field; applying avatar list anyway");
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
        debugLog("Can't locate end of version field");
        return;
    }

    string verStr = llStringTrim(llGetSubString(body, start, start + endVer - 1), STRING_TRIM);
    integer newVersion = (integer)verStr;

    if (newVersion != config_version) {
        debugLog("Config version " + (string)config_version + " -> " + (string)newVersion);
        updateAvatarList(newAvatars);
        config_version = newVersion;
    } else {
        debugLog("Config version unchanged: " + (string)config_version);
    }
}

integer findAvatarIndex(string uuid) {
    integer n = llGetListLength(tracked_avatars);
    integer i;
    for (i = 0; i < n; i += STRIDE) {
        if (llList2String(tracked_avatars, i) == uuid) return i;
    }
    return -1;
}

updateAvatarList(list newAvatars) {
    integer i;
    for (i = llGetListLength(tracked_avatars) - STRIDE; i >= 0; i -= STRIDE) {
        string uuid = llList2String(tracked_avatars, i);
        if (llListFindList(newAvatars, [uuid]) == -1) {
            debugLog("Removing avatar: " + uuid);
            tracked_avatars = llDeleteSubList(tracked_avatars, i, i + STRIDE - 1);
        }
    }

    integer count = llGetListLength(newAvatars);
    for (i = 0; i < count; ++i) {
        string uuid = llList2String(newAvatars, i);
        if (findAvatarIndex(uuid) == -1) {
            debugLog("Adding avatar: " + uuid);
            tracked_avatars += [uuid, -1, uuid, NULL_KEY];
        }
    }

    debugLog("Now tracking " + (string)(llGetListLength(tracked_avatars) / STRIDE) + " avatars");
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
        if (poll_cursor >= totalAvatars) poll_cursor = 0;
        integer idx = poll_cursor * STRIDE;
        poll_cursor++;

        string uuid = llList2String(tracked_avatars, idx);
        key k = (key)uuid;
        if (k == NULL_KEY) {
            debugLog("Invalid UUID at index " + (string)idx + ": " + uuid);
        } else {
            key q = llRequestAgentData(k, DATA_ONLINE);
            debugLog("Polling " + uuid + " -> query " + (string)q);
            tracked_avatars = llListReplaceList(tracked_avatars, [q], idx + 3, idx + 3);
        }
    }
}

processAvatarStatus(integer idx, integer isOnline) {
    integer wasOnline = llList2Integer(tracked_avatars, idx + 1);
    string uuid = llList2String(tracked_avatars, idx);

    // Clear pending query slot
    tracked_avatars = llListReplaceList(tracked_avatars, [NULL_KEY], idx + 3, idx + 3);

    debugLog("Status " + uuid + ": was=" + (string)wasOnline + " now=" + (string)isOnline);

    if (wasOnline == -1) {
        tracked_avatars = llListReplaceList(tracked_avatars, [isOnline], idx + 1, idx + 1);
        updateHoverText();

        // Optionally emit an event on first observation of online.
        // This catches avatars who were already online when the script started.
        if (EMIT_INITIAL_ONLINE && isOnline == 1) {
            debugLog("Emitting initial login event for " + uuid);
            sendEvent(uuid, "login");
        }
        return;
    }

    if (wasOnline == isOnline) return;

    string action;
    if (isOnline) action = "login";
    else action = "logout";
    debugLog(action + " detected for " + uuid);

    sendEvent(uuid, action);

    tracked_avatars = llListReplaceList(tracked_avatars, [isOnline], idx + 1, idx + 1);
    updateHoverText();
}

sendEvent(string uuid, string action) {
    string isoTime = llGetTimestamp();

    string json = "[{\"event_ts\":\"" + isoTime + "\","
                + "\"action\":\"" + action + "\","
                + "\"avatarKey\":\"" + uuid + "\","
                + "\"regionName\":\"global\"}]";

    httpPost(API_URL + "/api/events", json);
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

integer findPendingOnlineQuery(key qid) {
    integer n = llGetListLength(tracked_avatars);
    integer i;
    for (i = 0; i < n; i += STRIDE) {
        if ((key)llList2String(tracked_avatars, i + 3) == qid) return i;
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
            llOwnerSay("WARNING: API_URL is not https://");
        }

        llSetTimerEvent(5.0);
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
            debugLog("HTTP error " + (string)status + ": " + llGetSubString(body, 0, 200));
            return;
        }

        if (llSubStringIndex(body, "\"trackedAvatars\"") != -1) {
            processConfig(body);
            return;
        }

        if (llSubStringIndex(body, "\"received\"") != -1) {
            debugLog("Events received by server");
            return;
        }

        debugLog("Unhandled HTTP response: " + llGetSubString(body, 0, 100));
    }

    dataserver(key query_id, string data) {
        integer idx = findPendingOnlineQuery(query_id);
        if (idx != -1) {
            integer isOnline = (data == "1");
            processAvatarStatus(idx, isOnline);
            return;
        }

        debugLog("Unmatched dataserver query: " + (string)query_id + " data=" + data);
    }
}