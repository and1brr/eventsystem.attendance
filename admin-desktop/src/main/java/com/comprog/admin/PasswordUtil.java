package com.comprog.admin;

import java.security.MessageDigest;
import java.util.Base64;

public class PasswordUtil {
    public static String hash(String rawPassword) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(rawPassword.getBytes("UTF-8"));
            String encoded = Base64.getEncoder().encodeToString(hash);
            return "sha256:" + encoded;
        } catch (Exception e) {
            e.printStackTrace();
            return null;
        }
    }

    public static boolean matches(String rawPassword, String storedHash) {
        if (storedHash == null || !storedHash.startsWith("sha256:")) {
            return false;
        }
        
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(rawPassword.getBytes("UTF-8"));
            String encoded = Base64.getEncoder().encodeToString(hash);
            
            String expected = storedHash.substring(7);
            return expected.equals(encoded);
        } catch (Exception e) {
            e.printStackTrace();
            return false;
        }
    }
}