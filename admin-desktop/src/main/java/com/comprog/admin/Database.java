package com.comprog.admin;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

final class Database {
    private Database() {
    }

    static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(
            AppConfig.get("db.url", "jdbc:mysql://127.0.0.1:3306/event_attendance"),
            AppConfig.get("db.user", "root"),
            AppConfig.get("db.password", "")
        );
    }

    static void addUser(String username, String rawPassword, String fullName, int roleId) throws SQLException {
        String hash = PasswordUtil.hash(rawPassword);
        String sql = "INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (?, ?, ?, ?, 1)";
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, username);
            stmt.setString(2, hash);
            stmt.setString(3, fullName);
            stmt.setInt(4, roleId);
            stmt.executeUpdate();
        }
    }

    static void addEvent(String name, String date, String description, int createdBy) throws SQLException {
        String sql = "INSERT INTO events (event_name, event_date, event_description, created_by) VALUES (?, ?, ?, ?)";
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, name);
            stmt.setString(2, date);
            stmt.setString(3, description);
            stmt.setInt(4, createdBy);
            stmt.executeUpdate();
        }
    }

    static UserSession authenticate(String username, String password) throws SQLException {
        String sql = "SELECT u.id, u.username, u.password_hash, u.full_name, r.name AS role " +
            "FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.username = ? AND u.is_active = 1 LIMIT 1";
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setString(1, username);
            try (ResultSet resultSet = statement.executeQuery()) {
                if (!resultSet.next()) {
                    return null;
                }

                String storedHash = resultSet.getString("password_hash");
                if (!PasswordUtil.matches(password, storedHash)) {
                    return null;
                }

                return new UserSession(
                    resultSet.getInt("id"),
                    resultSet.getString("username"),
                    resultSet.getString("full_name"),
                    resultSet.getString("role")
                );
            }
        }
    }

    static int countStudents() throws SQLException {
        return count("SELECT COUNT(*) FROM students");
    }

    static int countLogs() throws SQLException {
        return count("SELECT COUNT(*) FROM scan_logs");
    }

    static int countTodayLogs() throws SQLException {
        return count("SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at) = CURDATE()");
    }

    static int countStaff() throws SQLException {
        return count("SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = 'Staff'");
    }

    static List<StudentRecord> fetchStudents(String search) throws SQLException {
        StringBuilder sql = new StringBuilder(
            "SELECT id, student_id, fname, lname, COALESCE(mname, '') AS mname, COALESCE(course, '') AS course, " +
                "COALESCE(year_level, '') AS year_level, COALESCE(section, '') AS section, status FROM students"
        );
        List<Object> params = new ArrayList<>();
        if (search != null && !search.isBlank()) {
            sql.append(" WHERE student_id LIKE ? OR fname LIKE ? OR lname LIKE ?");
            String needle = "%" + search.trim() + "%";
            params.add(needle);
            params.add(needle);
            params.add(needle);
        }
        sql.append(" ORDER BY lname, fname");

        return queryStudents(sql.toString(), params);
    }

    static List<LogRecord> fetchLogs(String search, String status) throws SQLException {
        StringBuilder sql = new StringBuilder(
            "SELECT sl.id, sl.student_id, s.student_id AS student_code, CONCAT(s.fname, ' ', s.lname) AS student_name, " +
                "sl.scan_code, sl.scan_type, sl.status, sl.scanned_at, u.full_name AS scanned_by " +
                "FROM scan_logs sl INNER JOIN students s ON s.id = sl.student_id " +
                "INNER JOIN users u ON u.id = sl.scanned_by WHERE 1=1"
        );
        List<Object> params = new ArrayList<>();
        if (search != null && !search.isBlank()) {
            sql.append(" AND (s.student_id LIKE ? OR s.fname LIKE ? OR s.lname LIKE ? OR sl.scan_code LIKE ?)");
            String needle = "%" + search.trim() + "%";
            for (int i = 0; i < 4; i++) {
                params.add(needle);
            }
        }
        if (status != null && !status.isBlank()) {
            sql.append(" AND sl.status = ?");
            params.add(status);
        }
        sql.append(" ORDER BY sl.scanned_at DESC LIMIT 500");

        List<LogRecord> records = new ArrayList<>();
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql.toString())) {
            for (int i = 0; i < params.size(); i++) {
                statement.setObject(i + 1, params.get(i));
            }
            try (ResultSet resultSet = statement.executeQuery()) {
                while (resultSet.next()) {
                    records.add(new LogRecord(
                        resultSet.getInt("id"),
                        resultSet.getInt("student_id"),
                        resultSet.getString("student_code"),
                        resultSet.getString("student_name"),
                        resultSet.getString("scan_code"),
                        resultSet.getString("scan_type"),
                        resultSet.getString("status"),
                        resultSet.getString("scanned_at"),
                        resultSet.getString("scanned_by")
                    ));
                }
            }
        }
        return records;
    }

    static void saveStudent(StudentRecord record) throws SQLException {
        String sql;
        boolean hasId = record.getId() > 0;
        if (hasId) {
            sql = "UPDATE students SET student_id=?, fname=?, lname=?, mname=?, course=?, year_level=?, section=?, status=? WHERE id=?";
        } else {
            sql = "INSERT INTO students (student_id, fname, lname, mname, course, year_level, section, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        }

        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setString(1, record.getStudentId());
            statement.setString(2, record.getFirstName());
            statement.setString(3, record.getLastName());
            statement.setString(4, record.getMiddleName());
            statement.setString(5, record.getCourse());
            statement.setString(6, record.getYearLevel());
            statement.setString(7, record.getSection());
            statement.setString(8, record.getStatus());
            if (hasId) {
                statement.setInt(9, record.getId());
            }
            statement.executeUpdate();
        }
    }

    static void deleteEvent(int id) throws SQLException {
        updateById("DELETE FROM events WHERE id = ?", id);
    }

    static void deleteUser(int id) throws SQLException {
        updateById("DELETE FROM users WHERE id = ?", id);
    }

    static void updateEvent(int id, String name, String date, String desc) throws SQLException {
        String sql = "UPDATE events SET event_name=?, event_date=?, event_description=? WHERE id=?";
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, name);
            stmt.setString(2, date);
            stmt.setString(3, desc);
            stmt.setInt(4, id);
            stmt.executeUpdate();
        }
    }

    static void updateUser(int id, String uname, String fname, int roleId, boolean isActive) throws SQLException {
        String sql = "UPDATE users SET username=?, full_name=?, role_id=?, is_active=? WHERE id=?";
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, uname);
            stmt.setString(2, fname);
            stmt.setInt(3, roleId);
            stmt.setInt(4, isActive ? 1 : 0);
            stmt.setInt(5, id);
            stmt.executeUpdate();
        }
    }

    static void updateStudentStatus(String studentId, String status) throws SQLException {
        String sql = "UPDATE students SET status=? WHERE student_id=?";
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, status);
            stmt.setString(2, studentId);
            stmt.executeUpdate();
        }
    }

    static void deleteMergedAttendance(String studentIdCode, String eventName) throws SQLException {
        String sql;
        if (eventName == null || eventName.equals("General")) {
            sql = "DELETE sl FROM scan_logs sl JOIN students s ON sl.student_id = s.id WHERE s.student_id = ? AND sl.event_id IS NULL";
        } else {
            sql = "DELETE sl FROM scan_logs sl JOIN students s ON sl.student_id = s.id JOIN events e ON sl.event_id = e.id WHERE s.student_id = ? AND e.event_name = ?";
        }
        try (Connection con = getConnection(); PreparedStatement stmt = con.prepareStatement(sql)) {
            stmt.setString(1, studentIdCode);
            if (eventName != null && !eventName.equals("General")) {
                stmt.setString(2, eventName);
            }
            stmt.executeUpdate();
        }
    }

    static void deleteStudent(int id) throws SQLException {
        updateById("DELETE FROM students WHERE id = ?", id);
    }

    static StudentRecord findStudent(int id) throws SQLException {
        String sql = "SELECT id, student_id, fname, lname, COALESCE(mname, '') AS mname, COALESCE(course, '') AS course, " +
            "COALESCE(year_level, '') AS year_level, COALESCE(section, '') AS section, status FROM students WHERE id = ?";
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setInt(1, id);
            try (ResultSet resultSet = statement.executeQuery()) {
                if (!resultSet.next()) {
                    return null;
                }
                return mapStudent(resultSet);
            }
        }
    }

    static void saveLog(LogRecord record) throws SQLException {
        String sql = "UPDATE scan_logs SET student_id=?, scan_code=?, scan_type=?, status=?, scanned_at=? WHERE id=?";
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setInt(1, record.getStudentId());
            statement.setString(2, record.getScanCode());
            statement.setString(3, record.getScanType());
            statement.setString(4, record.getStatus());
            statement.setString(5, record.getScannedAt());
            statement.setInt(6, record.getId());
            statement.executeUpdate();
        }
    }

    static void deleteLog(int id) throws SQLException {
        updateById("DELETE FROM scan_logs WHERE id = ?", id);
    }

    static LogRecord findLog(int id) throws SQLException {
        String sql = "SELECT sl.id, sl.student_id, s.student_id AS student_code, CONCAT(s.fname, ' ', s.lname) AS student_name, " +
            "sl.scan_code, sl.scan_type, sl.status, sl.scanned_at, u.full_name AS scanned_by " +
            "FROM scan_logs sl INNER JOIN students s ON s.id = sl.student_id INNER JOIN users u ON u.id = sl.scanned_by WHERE sl.id = ?";
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setInt(1, id);
            try (ResultSet resultSet = statement.executeQuery()) {
                if (!resultSet.next()) {
                    return null;
                }
                return new LogRecord(
                    resultSet.getInt("id"),
                    resultSet.getInt("student_id"),
                    resultSet.getString("student_code"),
                    resultSet.getString("student_name"),
                    resultSet.getString("scan_code"),
                    resultSet.getString("scan_type"),
                    resultSet.getString("status"),
                    resultSet.getString("scanned_at"),
                    resultSet.getString("scanned_by")
                );
            }
        }
    }

    static List<StudentRecord> fetchStudentOptions() throws SQLException {
        return fetchStudents(null);
    }

    private static List<StudentRecord> queryStudents(String sql, List<Object> params) throws SQLException {
        List<StudentRecord> records = new ArrayList<>();
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            for (int i = 0; i < params.size(); i++) {
                statement.setObject(i + 1, params.get(i));
            }
            try (ResultSet resultSet = statement.executeQuery()) {
                while (resultSet.next()) {
                    records.add(mapStudent(resultSet));
                }
            }
        }
        return records;
    }

    private static StudentRecord mapStudent(ResultSet resultSet) throws SQLException {
        return new StudentRecord(
            resultSet.getInt("id"),
            resultSet.getString("student_id"),
            resultSet.getString("fname"),
            resultSet.getString("lname"),
            resultSet.getString("mname"),
            resultSet.getString("course"),
            resultSet.getString("year_level"),
            resultSet.getString("section"),
            resultSet.getString("status")
        );
    }

    private static int count(String sql) throws SQLException {
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql); ResultSet resultSet = statement.executeQuery()) {
            return resultSet.next() ? resultSet.getInt(1) : 0;
        }
    }

    private static void updateById(String sql, int id) throws SQLException {
        try (Connection connection = getConnection(); PreparedStatement statement = connection.prepareStatement(sql)) {
            statement.setInt(1, id);
            statement.executeUpdate();
        }
    }
}
