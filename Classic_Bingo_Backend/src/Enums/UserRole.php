<?php 

namespace App\Enums;

enum UserRole: string {

    case ADMIN = 'admin';
    case USER = 'user';
    case OWNER = 'owner';
    case DEVELOPER = 'developer';

    public static function values(): array{
        return array_column(self::cases(),'value');
    }
} 