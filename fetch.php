<?php
// Disable some rules we don't need here
// phpcs:disable WordPress.NamingConventions, WordPress.WhiteSpace, WordPress.Security, Generic.WhiteSpace, WordPress.WP, Generic.Formatting.MultipleStatementAlignment, PEAR.Functions.FunctionCallSignature, WordPress.Arrays.ArrayIndentation, WordPress.Arrays.MultipleStatementAlignment, Generic.Arrays.DisallowShortArraySyntax, Squiz.PHP.CommentedOutCode, WordPress.PHP.YodaConditions, WordPress.PHP.DiscouragedPHPFunctions

// Fetch individual user json locally to a directory from following_accounts.csv
// Usage: php fetch.php
// Cron job for every 1 hour: 0 * * * * cd /home/mastodon/suomalaiset-mastodon-kayttajat && php /home/mastodon/suomalaiset-mastodon-kayttajat/fetch.php > /dev/null 2>&1

// Set up some variables
$csv = 'following_accounts.csv';
$dir = 'cache';
$csv_data = array_map('str_getcsv', file($csv));
$csv_data = array_slice($csv_data, 1); // Remove header row
$csv_data = array_map(null, ...$csv_data); // Transpose array
$csv_data = array_combine($csv_data[0], $csv_data[1]); // Make array associative

// Simple bash colors
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

// Only allow command line use
if (php_sapi_name() !== 'cli') {
  die('This script can only be run from the command line.');
}

// Function to check if user exists on their home instance (bulletproof check)
function checkUserExistsOnHomeInstance($acct) {
  // Split acct into username and instance
  $parts = explode('@', $acct);
  if (count($parts) !== 2) {
    return true; // Can't parse, assume exists to be safe
  }
  $username = $parts[0];
  $instance = $parts[1];

  // Check the home instance directly
  $url = "https://{$instance}/api/v1/accounts/lookup?acct={$username}";

  $context = stream_context_create([
    'http' => [
      'timeout' => 10,
      'ignore_errors' => true,
      'header' => 'User-Agent: FinnishMastodonUsers/1.0'
    ]
  ]);

  $response = @file_get_contents($url, false, $context);

  // Network error = assume user exists to be safe
  if ($response === false) {
    return true;
  }

  // Check HTTP status from response headers
  $httpCode = 0;
  if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
      if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
        $httpCode = (int)$matches[1];
      }
    }
  }

  // 404 or 410 = user definitively doesn't exist
  if ($httpCode === 404 || $httpCode === 410) {
    return false;
  }

  // Check if user is suspended (deleted account)
  if ($response) {
    $data = json_decode($response, true);
    if (isset($data['suspended']) && $data['suspended'] === true) {
      return false; // User is suspended/deleted
    }
    // Also check for error response
    if (isset($data['error'])) {
      return false; // API returned an error
    }
  }

  return true; // Any other case, assume exists to be safe
}

// Track users to remove from CSV
$users_to_remove = [];

// Fetch json from API
foreach ($csv_data as $key => $value) {
  // If key contains mastodon.testausserveri.fi, replace it with testausserveri.fi
  if ( strpos($key, 'mastodon.testausserveri.fi') !== false ) {
    $key = str_replace('mastodon.testausserveri.fi', 'testausserveri.fi', $key);
  }

  // Define json file, use username as file name
  $file = $dir . '/' . $key . '.json';

  // Get avatar URL from json entry
  $avatar_url = $value;

  // If file exists and it's less than 1 day old, skip it
  if ( file_exists( $file)  && filemtime( $file ) > strtotime( '-1 day' ) ) {
    echo "${yellow}" . $key . ' is under a day old, skipping' . "${reset}" . PHP_EOL;
    continue;

  } else {
    $url = 'https://mementomori.social/api/v1/accounts/lookup?acct=' . $key;
    $json = file_get_contents($url);
    $obj = json_decode($json);

    if (empty($obj) || isset($obj->error)) {
      echo "${yellow}No user found via federation for ${key}, checking home instance...${reset}" . PHP_EOL;

      // Double-check on home instance before giving up
      $original_key = $key;
      // Restore original key for home instance check (undo testausserveri transformation)
      foreach ($csv_data as $csv_key => $csv_val) {
        if (strpos($csv_key, explode('@', $key)[0]) === 0) {
          $original_key = $csv_key;
          break;
        }
      }

      if (!checkUserExistsOnHomeInstance($original_key)) {
        echo "${red}User ${original_key} confirmed DELETED on home instance - marking for removal${reset}" . PHP_EOL;
        $users_to_remove[] = $original_key;

        // Delete cache file if exists
        if (file_exists($file)) {
          unlink($file);
          echo "${red}Deleted cache file: ${file}${reset}" . PHP_EOL;
        }
      } else {
        echo "${yellow}User ${key} not found via federation but exists on home instance (federation issue)${reset}" . PHP_EOL;
      }
      continue;
    } else {
      file_put_contents($file, $json);
      echo "${green}User ${key} saved to ${file}${reset}" . PHP_EOL;
    }
  }
}

// Save number of users from csv to a file usercount.json
// Get files except usercount.json
$files = array_diff( scandir( $dir ), array( '..', '.', 'usercount.json', 'all-users.json' ) );

// Count files
$count = count( $files );

// Save count to file
file_put_contents( $dir . '/usercount.json', $count );

// Echo message
echo "${green}User count ${count} saved to ${dir}/usercount.json${reset}" . PHP_EOL;

// Generate combined all-users.json from CSV entries
$all_users = [];
foreach ($csv_data as $key => $value) {
  // Handle testausserveri.fi exception
  if ( strpos($key, 'mastodon.testausserveri.fi') !== false ) {
    $key = str_replace('mastodon.testausserveri.fi', 'testausserveri.fi', $key);
  }

  $file = $dir . '/' . $key . '.json';
  if ( file_exists( $file ) ) {
    $user_json = file_get_contents($file);
    $user_data = json_decode($user_json, true);
    if ($user_data && isset($user_data['id'])) {
      // Add original key (with instance) for reference
      $user_data['_csv_key'] = $key;
      $all_users[] = $user_data;
    }
  }
}

// Save combined JSON
file_put_contents( $dir . '/all-users.json', json_encode($all_users) );
echo "${green}All users combined into ${dir}/all-users.json (" . count($all_users) . " users)${reset}" . PHP_EOL;

// Remove deleted users from CSV if any were found
if (!empty($users_to_remove)) {
  echo PHP_EOL . "${red}Removing " . count($users_to_remove) . " deleted user(s) from CSV...${reset}" . PHP_EOL;

  // Read current CSV
  $csv_lines = file($csv);
  $new_csv_lines = [];

  foreach ($csv_lines as $line) {
    $should_keep = true;
    foreach ($users_to_remove as $remove_user) {
      if (strpos($line, $remove_user) === 0) {
        $should_keep = false;
        echo "${red}Removing from CSV: ${remove_user}${reset}" . PHP_EOL;
        break;
      }
    }
    if ($should_keep) {
      $new_csv_lines[] = $line;
    }
  }

  // Write updated CSV
  file_put_contents($csv, implode('', $new_csv_lines));
  echo "${green}CSV updated. Removed " . count($users_to_remove) . " user(s).${reset}" . PHP_EOL;
}
