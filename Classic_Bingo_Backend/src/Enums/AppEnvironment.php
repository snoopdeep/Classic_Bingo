<?php 

namespace App\Enums;

enum AppEnvironment : string {

    // Removed case APP_ENV = 'APP_ENV';
    case DEVELOPMENT = 'DEVELOPMENT';
    case PRODUCTION = 'PRODUCTION';
    case TESTING = 'TESTING'; 

}