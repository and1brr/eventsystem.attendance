package com.comprog.admin;

import com.formdev.flatlaf.FlatLightLaf;
import com.formdev.flatlaf.ui.FlatRoundBorder;

import javax.swing.*;
import javax.swing.border.EmptyBorder;
import javax.swing.border.LineBorder;
import java.awt.*;
import java.awt.event.ActionEvent;

public class AdminApp {

    public static void main(String[] args) {
        // Setup Modern FlatLaf
        FlatLightLaf.setup();
        UIManager.put("Button.arc", 12);
        UIManager.put("Component.arc", 12);
        UIManager.put("ProgressBar.arc", 12);
        UIManager.put("TextComponent.arc", 12);
        UIManager.put("ScrollBar.thumbArc", 12);
        UIManager.put("ScrollBar.thumbInsets", new Insets(2, 2, 2, 2));
        UIManager.put("Table.selectionBackground", new Color(220, 252, 231));
        UIManager.put("Table.selectionForeground", new Color(21, 128, 61));

        SwingUtilities.invokeLater(() -> {
            showLoginScreen();
        });
    }

    public static void showLoginScreen() {
        JFrame loginFrame = new JFrame("Admin Login");
        loginFrame.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        loginFrame.setSize(450, 450);
        loginFrame.setLocationRelativeTo(null);
        loginFrame.getContentPane().setBackground(new Color(243, 244, 246));

        JPanel card = new JPanel();
        card.setLayout(new BoxLayout(card, BoxLayout.Y_AXIS));
        card.setBackground(Color.WHITE);
        card.setBorder(BorderFactory.createCompoundBorder(
                new LineBorder(new Color(229, 231, 235), 1, true),
                new EmptyBorder(35, 40, 35, 40)
        ));

        // Logo / Title
        JLabel title = new JLabel("System Admin");
        title.setFont(new Font("Segoe UI", Font.BOLD, 26));
        title.setForeground(new Color(31, 41, 55));
        title.setAlignmentX(Component.CENTER_ALIGNMENT);

        JLabel subtitle = new JLabel("Log in to manage everything");
        subtitle.setFont(new Font("Segoe UI", Font.PLAIN, 13));
        subtitle.setForeground(new Color(107, 114, 128));
        subtitle.setAlignmentX(Component.CENTER_ALIGNMENT);
        
        JTextField userField = new JTextField();
        userField.putClientProperty("JTextField.placeholderText", "Username");
        userField.putClientProperty("JTextField.showClearButton", true);
        userField.setFont(new Font("Segoe UI", Font.PLAIN, 14));
        userField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 45));
        
        JPasswordField passField = new JPasswordField();
        passField.putClientProperty("JTextField.placeholderText", "Password");
        passField.putClientProperty("JTextField.showRevealButton", true); // Modern Toggle!
        passField.putClientProperty("JTextField.showClearButton", true);
        passField.setFont(new Font("Segoe UI", Font.PLAIN, 14));
        passField.setMaximumSize(new Dimension(Integer.MAX_VALUE, 45));

        JLabel errorLabel = new JLabel(" ");
        errorLabel.setForeground(new Color(220, 38, 38)); // Red-600
        errorLabel.setFont(new Font("Segoe UI", Font.PLAIN, 12));
        errorLabel.setAlignmentX(Component.CENTER_ALIGNMENT);

        JButton loginBtn = new JButton("Login securely");
        loginBtn.setBackground(new Color(22, 163, 74));
        loginBtn.setForeground(Color.WHITE);
        loginBtn.setFont(new Font("Segoe UI", Font.BOLD, 15));
        loginBtn.setMaximumSize(new Dimension(Integer.MAX_VALUE, 45));
        loginBtn.setFocusPainted(false);
        loginBtn.setCursor(new Cursor(Cursor.HAND_CURSOR));
        loginBtn.setAlignmentX(Component.CENTER_ALIGNMENT);
        
        // Progress bar for loading effect
        JProgressBar pb = new JProgressBar();
        pb.setIndeterminate(true);
        pb.setVisible(false);
        pb.setMaximumSize(new Dimension(Integer.MAX_VALUE, 4));
        pb.setForeground(new Color(22, 163, 74));

        loginBtn.addActionListener((ActionEvent e) -> {
            loginBtn.setEnabled(false);
            pb.setVisible(true);
            errorLabel.setText(" ");
            
            SwingWorker<UserSession, Void> worker = new SwingWorker<>() {
                @Override
                protected UserSession doInBackground() throws Exception {
                    Thread.sleep(500); // Simulate network load for modern feel
                    return Database.authenticate(userField.getText(), new String(passField.getPassword()));
                }

                @Override
                protected void done() {
                    loginBtn.setEnabled(true);
                    pb.setVisible(false);
                    try {
                        UserSession session = get();
                        if (session != null) {
                            loginFrame.dispose();
                            new MainFrame().setVisible(true);
                        } else {
                            errorLabel.setText("Invalid credentials or inactive user.");
                        }
                    } catch (Exception ex) {
                        errorLabel.setText("DB Error: " + ex.getMessage());
                    }
                }
            };
            worker.execute();
        });

        card.add(title);
        card.add(Box.createVerticalStrut(5));
        card.add(subtitle);
        card.add(Box.createVerticalStrut(30));
        card.add(userField);
        card.add(Box.createVerticalStrut(15));
        card.add(passField);
        card.add(Box.createVerticalStrut(15));
        card.add(pb);
        card.add(Box.createVerticalStrut(5));
        card.add(errorLabel);
        card.add(Box.createVerticalStrut(15));
        card.add(loginBtn);

        JPanel wrapper = new JPanel(new GridBagLayout());
        wrapper.setBackground(new Color(243, 244, 246));
        wrapper.add(card);

        loginFrame.add(wrapper);
        loginFrame.setVisible(true);
    }
}