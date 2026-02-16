DROP USER IF EXISTS 'hubtube'@'localhost';
CREATE USER 'hubtube'@'localhost' IDENTIFIED BY 'MjgQdPorMf';
GRANT ALL PRIVILEGES ON hubtube.* TO 'hubtube'@'localhost';
FLUSH PRIVILEGES;
