// Avatar Tracking System - LSL Script
// This script tracks avatar login/logout events globally using llRequestAgentData

// ========== CONFIGURATION ==========
string API_URL = "https://your-domain.com";
string API_KEY = "your-api-key-here";
float CONFIG_POLL_INTERVAL = 60.0;  // How often to fetch config (seconds)
float AVATAR_CHECK_INTERVAL = 60.0; // How often to check avatar status (seconds)
integer DEBUG = TRUE;

// ========== STATE ==========
list tracked_avatars = [];       // UUIDs as strings
list avatar_online = [];         // 0 = offline, 1 = online
list avatar_names = [];          // Display names for events
list pending_queries = [];       // query IDs per avatar index
integer config_version = -1;     // For change detection
float last_config_check = 0.0;   // When we last fetched config
float last_avatar_check = 0.0;   // When we last checked avatars
list pending_events = [];        // Events waiting to be sent

// ========== HTTP HELPERS ==========

key httpGet(string url) {
    if (DEBUG) llOwnerSay("DEBUG: GET " + url);
    return llHTTPRequest(
        url,
        [
            HTTP_METHOD, "GET",
            HTTP_HEADERS, ["X-API-Key: " + API_KEY]
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
            HTTP_HEADERS, ["X-API-Key: " + API_KEY]
        ],
        body
    );
}

// ========== CONFIG MANAGEMENT ==========

fetchConfig() {
    string url = API_URL + "/api/tracking-config";
    httpGet(url);
}

processConfig(string body) {
    // Parse JSON response
    // Expected format: {"trackedAvatars": ["uuid1", "uuid2"], "version": 123, "pollInterval": 60}
    
    // Simple JSON parsing (LSL doesn't have built-in JSON parser)
    // Extract trackedAvatars array
    integer start = llSubStringIndex(body, "\"trackedAvatars\":[");
    if (start == -1) {
        if (DEBUG) llOwnerSay("DEBUG: No trackedAvatars in config");
        return;
    }
    
    start = start + 17; // Skip past "trackedAvatars":[
    integer end = llSubStringIndex(llGetSubString(body, start, -1), "]");
    if (end == -1) {
        if (DEBUG) llOwnerSay("DEBUG: Malformed trackedAvatars array");
        return;
    }
    
    string arrayStr = llGetSubString(body, start, start + end - 1);
    
    // Parse UUIDs from array
    list newAvatars = [];
    integer i = 0;
    integer len = llStringLength(arrayStr);
    
    while (i < len) {
        // Find next quote
        integer quote1 = llSubStringIndex(llGetSubString(arrayStr, i, -1), "\"");
        if (quote1 == -1) break;
        
        // Find closing quote
        integer subStart = i + quote1 + 1;
        integer quote2 = llSubStringIndex(llGetSubString(arrayStr, subStart, -1), "\"");
        if (quote2 == -1) break;
        
        string uuid = llGetSubString(arrayStr, subStart, subStart + quote2 - 1);
        uuid = llToLower(llStringTrim(uuid, STRING_TRIM));
        
        if (llStringLength(uuid) == 36) {
            newAvatars += [uuid];
        }
        
        i = subStart + quote2 + 1;
    }
    
    // Extract version
    start = llSubStringIndex(body, "\"version\":");
    if (start != -1) {
        start += 10;
        integer comma = llSubStringIndex(llGetSubString(body, start, -1), ",");
        integer closeBrace = llSubStringIndex(llGetSubString(body, start, -1), "}");
        
        integer endVer = (comma != -1 && comma < closeBrace) ? comma : closeBrace;
        string verStr = llGetSubString(body, start, start + endVer - 1);
        verStr = llStringTrim(verStr, STRING_TRIM);
        integer newVersion = (integer)verStr;
        
        if (newVersion != config_version) {
            if (DEBUG) llOwnerSay("DEBUG: Config version changed: " + (string)config_version + " -> " + (string)newVersion);
            updateAvatarList(newAvatars);
            config_version = newVersion;
        } else {
            if (DEBUG) llOwnerSay("DEBUG: Config version unchanged: " + (string)config_version);
        }
    }
}

updateAvatarList(list newAvatars) {
    // Remove avatars no longer in the list
    integer i;
    integer count = llGetListLength(tracked_avatars);
    
    for (i = count - 1; i >= 0; --i) {
        string uuid = llList2String(tracked_avatars, i);
        if (llListFindList(newAvatars, [uuid]) == -1) {
            if (DEBUG) llOwnerSay("DEBUG: Removing avatar: " + uuid);
            tracked_avatars = llDeleteSubList(tracked_avatars, i);
            avatar_online = llDeleteSubList(avatar_online, i);
            avatar_names = llDeleteSubList(avatar_names, i);
            pending_queries = llDeleteSubList(pending_queries, i);
        }
    }
    
    // Add new avatars
    count = llGetListLength(newAvatars);
    for (i = 0; i < count; ++i) {
        string uuid = llList2String(newAvatars, i);
        if (llListFindList(tracked_avatars, [uuid]) == -1) {
            if (DEBUG) llOwnerSay("DEBUG: Adding avatar: " + uuid);
            tracked_avatars += [uuid];
            avatar_online += [-1]; // Unknown status initially
            avatar_names += [uuid]; // Will fetch name when they come online
            pending_queries += [NULL_KEY];
        }
    }
    
    if (DEBUG) llOwnerSay("DEBUG: Now tracking " + (string)llGetListLength(tracked_avatars) + " avatars");
}

// ========== AVATAR TRACKING ==========

pollAvatars() {
    integer n = llGetListLength(tracked_avatars);
    integer i;
    
    for (i = 0; i < n; ++i) {
        string uuid = llList2String(tracked_avatars, i);
        key k = (key)uuid;
        
        if (k != NULL_KEY) {
            key q = llRequestAgentData(k, DATA_ONLINE);
            pending_queries = llListReplaceList(pending_queries, [q], i, i);
        } else if (DEBUG) {
            llOwnerSay("DEBUG: Invalid UUID at index " + (string)i + ": " + uuid);
        }
    }
}

processAvatarStatus(integer idx, integer isOnline) {
    integer wasOnline = llList2Integer(avatar_online, idx);
    
    if (wasOnline == -1) {
        // First check, just record status
        avatar_online = llListReplaceList(avatar_online, [isOnline], idx, idx);
        if (DEBUG) llOwnerSay("DEBUG: Initial status for " + llList2String(tracked_avatars, idx) + ": " + (string)isOnline);
        return;
    }
    
    if (wasOnline != isOnline) {
        // Status changed!
        string uuid = llList2String(tracked_avatars, idx);
        string action = isOnline ? "login" : "logout";
        
        if (DEBUG) llOwnerSay("DEBUG: " + action + " detected for " + uuid);
        
        // Fetch name for the event
        key nameKey = llRequestAgentData((key)uuid, DATA_NAME);
        // Store the action to send after we get the name
        // We'll use a list: [query_id, uuid, action, timestamp]
        pending_events += [nameKey, uuid, action, llGetUnixTime()];
        
        // Update status
        avatar_online = llListReplaceList(avatar_online, [isOnline], idx, idx);
        
        // Update floating text
        updateHoverText();
    }
}

sendEvent(string uuid, string action, string displayName, string username, integer timestamp) {
    // Format timestamp as ISO 8601
    string isoTime = llGetTimestamp();
    
    // Build JSON event
    string json = "[{\"event_ts\":\"" + isoTime + "\",";
    json += "\"action\":\"" + action + "\",";
    json += "\"avatarKey\":\"" + uuid + "\",";
    json += "\"displayName\":\"" + displayName + "\",";
    json += "\"username\":\"" + username + "\",";
    json += "\"regionName\":\"global\"}]";
    
    string url = API_URL + "/api/events";
    httpPost(url, json);
}

updateHoverText() {
    integer n = llGetListLength(tracked_avatars);
    if (n == 0) {
        llSetText("No avatars being tracked", <1,1,1>, 1.0);
        return;
    }
    
    integer onlineCount = 0;
    integer i;
    
    for (i = 0; i < n; ++i) {
        if (llList2Integer(avatar_online, i) == 1) {
            onlineCount++;
        }
    }
    
    string text = "Tracking " + (string)n + " avatars\n";
    text += "Online: " + (string)onlineCount + " / Offline: " + (string)(n - onlineCount);
    
    llSetText(text, <1,1,1>, 1.0);
}

// ========== MAIN ==========

default {
    state_entry() {
        llOwnerSay("Avatar Tracking System starting...");
        llOwnerSay("API URL: " + API_URL);
        
        if (API_KEY == "your-api-key-here") {
            llOwnerSay("ERROR: Please configure API_KEY in the script!");
        }
        
        llSetTimerEvent(1.0); // Start timer
        updateHoverText();
    }
    
    on_rez(integer start_param) {
        llResetScript();
    }
    
    changed(integer change) {
        if (change & CHANGED_OWNER) {
            llResetScript();
        }
    }
    
    timer() {
        float now = llGetTime();
        
        // Check if we need to fetch config
        if (now - last_config_check >= CONFIG_POLL_INTERVAL) {
            fetchConfig();
            last_config_check = now;
        }
        
        // Check if we need to poll avatars
        if (now - last_avatar_check >= AVATAR_CHECK_INTERVAL) {
            pollAvatars();
            last_avatar_check = now;
        }
    }
    
    http_response(key request_id, integer status, list metadata, string body) {
        if (status != 200) {
            if (DEBUG) llOwnerSay("DEBUG: HTTP error " + (string)status + ": " + llGetSubString(body, 0, 200));
            return;
        }
        
        // Check if this is a config response
        if (llSubStringIndex(body, "\"trackedAvatars\"") != -1) {
            processConfig(body);
            return;
        }
        
        // Check if this is an event response
        if (llSubStringIndex(body, "\"received\"") != -1) {
            if (DEBUG) llOwnerSay("DEBUG: Events received by server");
            return;
        }
        
        if (DEBUG) llOwnerSay("DEBUG: Unhandled HTTP response: " + llGetSubString(body, 0, 100));
    }
    
    dataserver(key query_id, string data) {
        // Check if this is an avatar online status query
        integer idx = llListFindList(pending_queries, [query_id]);
        if (idx != -1) {
            integer isOnline = (data == "1");
            processAvatarStatus(idx, isOnline);
            pending_queries = llListReplaceList(pending_queries, [NULL_KEY], idx, idx);
            return;
        }
        
        // Check if this is a name query for pending events
        integer eventIdx = llListFindList(pending_events, [query_id]);
        if (eventIdx != -1 && (eventIdx % 4) == 0) {
            string uuid = llList2String(pending_events, eventIdx + 1);
            string action = llList2String(pending_events, eventIdx + 2);
            integer timestamp = llList2Integer(pending_events, eventIdx + 3);
            
            // Parse name: "DisplayName Resident" or just "DisplayName"
            list nameParts = llParseString2List(data, [" "], []);
            string displayName = llList2String(nameParts, 0);
            string username = llList2String(nameParts, 1);
            
            if (username == "Resident") {
                username = llToLower(llGetSubString(displayName, 0, 0)) + 
                           llGetSubString(displayName, 1, -1);
            } else {
                username = llToLower(username);
            }
            
            sendEvent(uuid, action, displayName, username, timestamp);
            
            // Remove from pending events
            pending_events = llDeleteSubList(pending_events, eventIdx, eventIdx + 3);
            
            return;
        }
    }
}
