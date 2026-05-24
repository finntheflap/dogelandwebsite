package vn.dogeland.sync.queue;

/**
 * Handler cho 1 action type. Được gọi từ ASYNC thread sau khi action đã được claim.
 * Nếu cần đụng inventory player → tự schedule lên main thread bằng Bukkit.getScheduler().
 *
 * Khi xử lý xong, gọi callback.done(resultJson) hoặc callback.fail(reason) để mark action.
 */
public interface ActionHandler {
    String type();
    void process(Action action, ResultCallback callback);

    interface ResultCallback {
        void done(String resultJson);
        void fail(String reason);
    }
}
