package com.comprog.admin;

final class UserSession {
    private final int id;
    private final String username;
    private final String fullName;
    private final String role;

    UserSession(int id, String username, String fullName, String role) {
        this.id = id;
        this.username = username;
        this.fullName = fullName;
        this.role = role;
    }

    int getId() {
        return id;
    }

    String getUsername() {
        return username;
    }

    String getFullName() {
        return fullName;
    }

    String getRole() {
        return role;
    }

    boolean isAdmin() {
        return "Admin".equalsIgnoreCase(role);
    }
}
