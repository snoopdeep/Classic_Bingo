<?php 

namespace App\Controllers;

use App\Services\UserService;

class AuthController 
{
    private UserService $userService;

    public function __construct() 
    {
        $this->userService = new UserService();
    }

   
     //create new guest user
     
    public function createGuest(): void 
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->userService->registerUser($data);
    }

   
     // Get current user info
    
    public function getMe(): void 
    {
        $this->userService->getCurrentUserProfile();
    }

  
    //Get user by ID

    public function getUser(array $params): void 
    {
        $userId = $params['userId'] ?? '';
        $this->userService->getUserProfile($userId);
    }


     //Logout user

    public function logout(): void 
    {
        $this->userService->logout();
    }
}

