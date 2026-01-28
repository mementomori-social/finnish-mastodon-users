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

    if (empty($obj)) {
      echo "${red}No user found for ${key}${reset}" . PHP_EOL;
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
