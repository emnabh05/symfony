# Fitopia App

## Overview
Fitopia is a fullstack web platform for fitness and wellness management. It provides user authentication, profile and onboarding management, personalized fitness/nutrition workflows, events/community interactions, and related services.

Developed at Esprit School of Engineering (Academic Year 2025-2026).

## Features
- Secure user management (registration, login, profile, role-based access)
- Health and fitness modules (plans, goals, nutrition-related tracking)
- Community modules (events, interactions, messaging, notifications)

## Tech Stack

### Frontend
- Twig templates
- HTML/CSS
- JavaScript
- Bootstrap

### Backend
- PHP 8.2+
- Symfony 7.4
- Doctrine ORM 3.6
- MySQL / MariaDB
- PHPUnit

## Architecture
The project follows a layered Symfony MVC architecture:
- Controllers for HTTP request handling
- Services for business logic
- Doctrine Entities/Repositories for persistence
- Twig templates for server-side rendering

## Contributors
- Omar Selmi
- Team members: update this section with full names

## Academic Context
Developed at Esprit School of Engineering - Tunisia  
PIDEV - 3A49 | 2025-2026

## Getting Started
1. Clone repository
2. Install dependencies
   - `composer install`
3. Configure environment
   - Copy `.env` values and set database/API keys
4. Run project
   - `php bin/console doctrine:migrations:migrate`
   - `symfony server:start` (or `php -S 127.0.0.1:8000 -t public`)

## Acknowledgments
Special thanks to Esprit professors, supervisors, and mentors for their guidance throughout this project.
