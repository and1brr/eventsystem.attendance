package com.comprog.admin;

public class StudentRecord {
    private int id;
    private String studentId;
    private String fname;
    private String lname;
    private String mname;
    private String course;
    private String yearLevel;
    private String section;
    private String status;

    public StudentRecord(int id, String studentId, String fname, String lname, String mname, String course, String yearLevel, String section, String status) {
        this.id = id;
        this.studentId = studentId;
        this.fname = fname;
        this.lname = lname;
        this.mname = mname;
        this.course = course;
        this.yearLevel = yearLevel;
        this.section = section;
        this.status = status;
    }

    public int getId() { return id; }
    public String getStudentId() { return studentId; }
    public String getFirstName() { return fname; }
    public String getLastName() { return lname; }
    public String getMiddleName() { return mname; }
    public String getCourse() { return course; }
    public String getYearLevel() { return yearLevel; }
    public String getSection() { return section; }
    public String getStatus() { return status; }
}