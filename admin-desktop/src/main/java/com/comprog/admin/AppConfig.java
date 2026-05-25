package com.comprog.admin;

import java.io.InputStream;
import java.util.Properties;

public class AppConfig {
    private static final Properties properties = new Properties();

    static {
        try (InputStream in = AppConfig.class.getResourceAsStream("/app.properties")) {
            if (in != null) {
                properties.load(in);
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public static String get(String key, String defaultValue) {
        return properties.getProperty(key, defaultValue);
    }
}