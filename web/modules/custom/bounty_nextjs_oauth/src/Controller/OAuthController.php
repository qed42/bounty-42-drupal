<?php

namespace Drupal\bounty_nextjs_oauth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

/**
 * Next.js Google OAuth Controller.
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

      $user_created = FALSE;

      if (!empty($existing_users)) {
        // User exists.
        $user = reset($existing_users);
      }
      else {
        // Create new user.
        $user = User::create([
          'name' => $this->generateUsername($email, $name),
          'mail' => $email,
          'status' => 1,
          'roles' => ['authenticated'],
        ]);
        $user->save();
        $user_created = TRUE;
      }

      // Check if user is in any project team.
      try {
        $is_in_project_team = $this->isUserInAnyProjectTeam($user);
      }
      catch (\Exception $e) {
        \Drupal::logger('bounty_nextjs_oauth')->error('Project team check failed: @message', [
          '@message' => $e->getMessage(),
        ]);
        // Fallback to false.
        $is_in_project_team = FALSE;
      }

      return new JsonResponse([
        'status' => $user_created ? 'created' : 'exists',
        'uid' => $user->id(),
        'uuid' => $user->uuid(),
        'email' => $user->getEmail(),
        'name' => $user->getDisplayName(),
        'is_in_project_team' => $is_in_project_team,
      ]);
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
      $name_parts = explode(' ', trim($name));
      if (count($name_parts) >= 2) {
        $first_name = strtolower($name_parts[0]);
        $last_name = strtolower($name_parts[count($name_parts) - 1]);
      }
      else {
        $first_name = strtolower($name_parts[0]);
        $last_name = strtolower(explode('@', $email)[0]);
      }
    }
    else {
      $email_parts = explode('@', $email);
      $first_name = strtolower($email_parts[0]);
      $last_name = 'user';
    }

    $first_name = preg_replace('/[^a-zA-Z0-9]/', '', $first_name);
    $last_name = preg_replace('/[^a-zA-Z0-9]/', '', $last_name);

    $base_username = $first_name . '.' . $last_name;
    $username = $base_username;
    $counter = 1;

    while ($this->usernameExists($username)) {
      $username = $base_username . '_' . $counter;
      $counter++;
    }

    return $username;
  }

  /**
   * Check if a username already exists.
   */
  private function usernameExists($username) {
    $existing = $this->entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);

    return !empty($existing);
  }

  /**
   * Check if user is part of any project team.
   */
  private function isUserInAnyProjectTeam(User $user): bool {
    $user_id = $user->id();

    // Step 1: Get all taxonomy terms (project teams) where this user is a member.
    $term_query = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'project_teams')
      ->condition('field_team_members.target_id', $user_id)
    // Just in case.
      ->range(0, 50);

    $term_ids = $term_query->execute();

    \Drupal::logger('oauth_debug')->notice('Found team term IDs for user @uid: @terms', [
      '@uid' => $user_id,
      '@terms' => implode(', ', array_keys($term_ids)),
    ]);

    if (empty($term_ids)) {
      return FALSE;
    }

    // Step 2: Get any project nodes that reference these team terms in field_teams.
    $project_query = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'project')
      ->condition('field_teams.target_id', array_keys($term_ids), 'IN')
      ->range(0, 1);

    $project_ids = $project_query->execute();

    \Drupal::logger('oauth_debug')->notice('Matching project node IDs: @ids', [
      '@ids' => implode(', ', $project_ids),
    ]);

    return !empty($project_ids);
  }

}
