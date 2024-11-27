# Telegram Bot "Secret Santa" with Docker and Ngrok

This project is a Telegram bot designed to run in Docker containers with webhook integration using Ngrok. Follow the instructions below to set up and run the bot.

---

## Prerequisites

Before starting, ensure you have the following installed:

- **Docker** and **Docker Compose**.
- **Composer** for managing PHP dependencies.

---

## Installation and Setup

### 1. Install Composer (if not already installed)

To install Composer, follow the official installation guide: [Composer Installation](https://getcomposer.org/doc/00-intro.md).

Or, run the following commands:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Install PHP Dependencies

Use Composer to install the required dependencies:

```bash
composer require telegram-bot/api
```

### 3. Install and Set Up Ngrok

* Download and install Ngrok from Ngrok's official website.
* Authenticate Ngrok with your account:

```bash
ngrok config add-authtoken <YOUR_AUTH_TOKEN>
```

---

## Running the Bot

### 4. Start Ngrok

Run Ngrok to forward your local port (e.g., 8081):

```bash
nohup ngrok http http://localhost:8081 > ngrok.log 2>&1 &
curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url'
```

and kill it by using

```bash
pkill ngrok
```

This will generate an HTTPS URL, such as https://6328-12-34-56-78.ngrok-free.app.

### 5. Set Telegram Webhook

Replace <BOT_API_KEY> with your bot token and set the generated Ngrok URL as the webhook:

```bash
curl -F "url=https://6328-82-77-78-56.ngrok-free.app" "https://api.telegram.org/bot<YOUR_BOT_API_KEY>/setWebhook"
```

To verify that the webhook is correctly set, run:

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_API_KEY>/getWebhookInfo"
```

### 6. Edit docker-compose.yml

Update the following values in docker-compose.yml:

* Port: Match the port exposed in Ngrok (e.g., 8081).
* Time Zone: Set your desired timezone (e.g., Europe/Bucharest).
* Bot Token and Username: Add your bot token and username as environment variables BOT_API_KEY and BOT_USERNAME.

Example:

```yaml
services:
  bot:
    environment:
      - BOT_API_KEY=YOUR_BOT_API_KEY
      - BOT_USERNAME=secretsantarobot
      - TZ=Europe/Paris
```

### 7. Build and Run Docker Containers

Run the bot using Docker Compose:

```bash
sudo docker compose up --build
```

---

## Enjoy!

Your bot should now be running! Use Telegram to interact with it and test its functionality.

---

## Troubleshooting

* Webhook Issues: Ensure the webhook is correctly set by verifying with getWebhookInfo.

* Container Logs: Check Docker logs for errors:

```bash
docker logs <container_name>
```

* Ngrok Disconnection: If Ngrok disconnects, restart it and update the webhook with the new Ngrok URL.