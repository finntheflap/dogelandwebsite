package vn.dogeland.sync.db;

import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;
import org.bukkit.configuration.ConfigurationSection;

import java.sql.Connection;
import java.sql.SQLException;
import java.util.logging.Logger;

public final class Database {

    private final HikariDataSource ds;

    public Database(ConfigurationSection cfg, Logger log) {
        HikariConfig hc = new HikariConfig();
        String host = cfg.getString("host", "localhost");
        int port    = cfg.getInt("port", 3306);
        String name = cfg.getString("name", "minecraft");
        String user = cfg.getString("user", "root");
        String pass = cfg.getString("password", "");

        // characterEncoding là Java charset name (UTF-8), không phải MySQL server charset (utf8mb4).
        // connectionCollation set client → utf8mb4 cho phép emoji + ký tự rộng.
        hc.setJdbcUrl("jdbc:mysql://" + host + ":" + port + "/" + name
                + "?useSSL=false&characterEncoding=UTF-8&useUnicode=true"
                + "&connectionCollation=utf8mb4_unicode_ci"
                + "&allowPublicKeyRetrieval=true&serverTimezone=UTC");
        hc.setUsername(user);
        hc.setPassword(pass);
        hc.setMaximumPoolSize(cfg.getInt("pool-size", 6));
        hc.setConnectionTimeout(cfg.getLong("connection-timeout-ms", 5000));
        hc.setPoolName("DogelandSync-Hikari");
        hc.addDataSourceProperty("cachePrepStmts", "true");
        hc.addDataSourceProperty("prepStmtCacheSize", "250");
        hc.addDataSourceProperty("prepStmtCacheSqlLimit", "2048");

        this.ds = new HikariDataSource(hc);
        log.info("Connected to MySQL " + host + ":" + port + "/" + name);
    }

    public Connection get() throws SQLException {
        return ds.getConnection();
    }

    public void close() {
        if (ds != null && !ds.isClosed()) ds.close();
    }
}
