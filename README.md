# TripSync – Smart Travel Planning & Booking Platform

## Project Overview

TripSync is a full-stack web-based tourism management platform developed as a Final Year Software Engineering Project. The system was designed to centralize travel planning, accommodation booking, transportation management, itinerary creation, real-time tracking, and payment processing within a unified digital environment tailored for the Sri Lankan tourism industry.

The platform addresses the common problem of fragmented tourism services where travelers must use multiple disconnected platforms for accommodation, transport, and travel coordination. TripSync integrates these services into a single system to improve operational efficiency, user convenience, and service coordination.

---



## Technology Stack

| Category | Technologies |
|---|---|
| Frontend | HTML5, CSS3, JavaScript (ES6), Bootstrap 5, Tailwind CSS |
| Backend | PHP 8.x, AJAX |
| Database | MySQL Relational Database |
| APIs and Services | Google Places API, Google Distance Matrix API, PayHere Sandbox, QRServer API |
| Visualization and Mapping | Chart.js, Leaflet.js, OpenStreetMap |
| Notifications | Firebase Cloud Messaging (FCM) |

---


## Installation and Setup

### Prerequisites

- XAMPP or WAMP
- PHP 8.x
- MySQL
- Google Cloud API Key

### Setup Instructions

#### 1. Clone the Repository

```bash
git clone https://github.com/ShihanSenevirathna/TripSync
```

#### 2. Move the Project

Move the project folder into:

```text
C:/xampp/htdocs/
```

#### 3. Create the Database

Create a database named:

```text
tripsync_db
```

#### 4. Import Database File

Import the SQL file located in:

```text
/database/tripsync_db.sql
```

#### 5. Configure Environment Variables

Open:

```text
/includes/config.php
```

Update:
- Database credentials
- Google API Key
- PayHere sandbox credentials

#### 6. Start the Server

Start:
- Apache
- MySQL

using XAMPP or WAMP.

#### 7. Run the Application

Open the browser and navigate to:

```text
http://localhost/TripSync
```

---
