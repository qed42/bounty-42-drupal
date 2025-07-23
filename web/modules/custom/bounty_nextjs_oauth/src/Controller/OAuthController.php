<?php

namespace Drupal\bounty_nextjs_oauth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Nextjs Google Oauth Controller.
 */
class OAuthController extends ControllerBase {

  /**
   * Sync user from Next.js OAuth and create/update Drupal user.
   */
  public function syncUser(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($data['email'])) {
      return new JsonResponse(['error' => 'Email is required'], 400);
    }

    $email = $data['email'];
    $name = $data['name'] ?? '';

    // Validate email domain.
    if (!str_ends_with($email, '@qed42.com')) {
      return new JsonResponse(['error' => 'Invalid email domain'], 403);
    }

    try {
      // Check if user exists by email.
      $existing_users = $this->entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $email]);

      if (!empty($existing_users)) {
        // User exists, return their info.
        $user = reset($existing_users);

        return new JsonResponse([
          'status' => 'exists',
          'uid' => $user->id(),
          'uuid' => $user->uuid(),
          'email' => $user->getEmail(),
          'name' => $user->getDisplayName(),
        ]);
      }
      else {
        // Create new user.
        $user = User::create([
          'name' => $this->generateUsername($email, $name),
          'mail' => $email,
        // Active.
          'status' => 1,
          'roles' => ['authenticated'],
        ]);

        $user->save();

        return new JsonResponse([
          'status' => 'created',
          'uid' => $user->id(),
          'uuid' => $user->uuid(),
          'email' => $user->getEmail(),
          'name' => $user->getDisplayName(),
        ]);
      }
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'Internal server error'], 500);
    }
  }

  /**
   * Generate a unique username in first_name.last_name format.
   */
  private function generateUsername($email, $name) {
    if (!empty($name)) {
      // Split the full name into parts.
      $name_parts = explode(' ', trim($name));

      if (count($name_parts) >= 2) {
        // Use first and last name.
        $first_name = strtolower($name_parts[0]);
        $last_name = strtolower($name_parts[count($name_parts) - 1]);
      }
      else {
        // Only one name provided, use email prefix as last name.
        $first_name = strtolower($name_parts[0]);
        $last_name = strtolower(explode('@', $email)[0]);
      }
    }
    else {
      // No name provided, use email prefix and domain.
      $email_parts = explode('@', $email);
      $first_name = strtolower($email_parts[0]);
      // Default fallback.
      $last_name = 'user';
    }

    // Clean the names (remove special characters, keep only letters and numbers)
    $first_name = preg_replace('/[^a-zA-Z0-9]/', '', $first_name);
    $last_name = preg_replace('/[^a-zA-Z0-9]/', '', $last_name);

    // Create base username in first_name.last_name format.
    $base_username = $first_name . '.' . $last_name;

    // Ensure uniqueness.
    $username = $base_username;
    $counter = 1;

    while ($this->usernameExists($username)) {
      $username = $base_username . '_' . $counter;
      $counter++;
    }

    return $username;
  }

  /**
   * Check if a username already exists in the system.
   */
  private function usernameExists($username) {
    $existing = $this->entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);

    return !empty($existing);
  }

}
