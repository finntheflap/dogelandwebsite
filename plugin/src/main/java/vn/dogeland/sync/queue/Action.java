package vn.dogeland.sync.queue;

import com.google.gson.JsonObject;

/**
 * 1 row trong web_inv_actions sau khi parsed.
 * payload là JSON object tuỳ action type — xem từng handler.
 */
public record Action(long id, String username, String mode, String type,
                     JsonObject payload, String requestedBy) {
}
