package com.comprog.admin;

public class LogRecord {
    private int id;
    private int studentId;
    private String studentCode;
    private String studentName;
    private String scanCode;
    private String scanType;
    private String status;
    private String scannedAt;
    private String scannedBy;

    public LogRecord(int id, int studentId, String studentCode, String studentName, String scanCode, String scanType, String status, String scannedAt, String scannedBy) {
        this.id = id;
        this.studentId = studentId;
        this.studentCode = studentCode;
        this.studentName = studentName;
        this.scanCode = scanCode;
        this.scanType = scanType;
        this.status = status;
        this.scannedAt = scannedAt;
        this.scannedBy = scannedBy;
    }

    public int getId() { return id; }
    public int getStudentId() { return studentId; }
    public String getScanCode() { return scanCode; }
    public String getScanType() { return scanType; }
    public String getStatus() { return status; }
    public String getScannedAt() { return scannedAt; }
}