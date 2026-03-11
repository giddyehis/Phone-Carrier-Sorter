# Phonetool

Simple PHP tools for working with South African mobile numbers. Will scale to worldwide when i have the time.
Added WhatsApp tool for automating mass sending. Still in production...

## Features

- Sort pasted numbers by original mobile carrier (Vodacom, MTN, Cell C, Telkom).
- Check which numbers have WhatsApp using WhatsApp Cloud API.
- Send bulk WhatsApp text messages via WhatsApp Cloud API.

## Requirements

- PHP 8.1+ with cURL extension enabled.
- A WhatsApp Cloud API app (Meta/Facebook Developer):
  - WhatsApp access token.
  - WhatsApp phone number ID.

## Setup

1. **Install PHP dependencies**

No external libraries are required; the project uses only built-in PHP extensions.

2. **Configure WhatsApp Cloud API**

Copy the sample config file and fill in your real values:

```bash
cp config.sample.php config.php
```

Edit `config.php` and set:

- `WHATSAPP_ACCESS_TOKEN` – your permanent or long‑lived access token.
- `WHATSAPP_PHONE_NUMBER_ID` – the phone number ID from the WhatsApp Cloud API dashboard.

> Do not commit the real `config.php` to version control.

## Running the app

From the `phonetool` directory:

```bash
php -S localhost:8000
```

Then open in your browser:

- Carrier sorter: `http://localhost:8000/index.php`
- WhatsApp tools (check + bulk send): `http://localhost:8000/whatsapp.php`
