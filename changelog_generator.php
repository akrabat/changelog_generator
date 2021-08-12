#!/usr/bin/env php
<?php
/**
 * Generate a markdown changelog based on a GitHub milestone.
 * Forked from https://github.com/weierophinney/changelog_generator (Copyright (c) 2013 Matthew Weier O'Phinney)
 */

use App\Getopt;
use App\HttpClient;

ini_set('display_errors', true);
error_reporting(E_ALL | E_STRICT);

// Autoloading based on phpunit's approach
$autoloadLocations = array(
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
    getcwd() . '/vendor/autoload.php',
);

foreach ($autoloadLocations as $location) {
    if (! file_exists($location)) {
        continue;
    }

    $autoloader = require $location;

    break;
}

if (empty($autoloader)) {
    file_put_contents(
        'php://stderr',
        "Failed to discover autoloader; please install dependencies and/or install via Composer." . PHP_EOL
    );
    exit(1);
}

// Get configuration
$config          = getConfig();
$token           = $config['token'];             // Github API token
$user            = $config['user'];              // Your user or organization
$repo            = $config['repo'];              // The repository you're getting the changelog for
$milestone       = $config['milestone'];         // The milestone ID
$title           = $config['title'];             // The milestone title which is often the designated version tag
$plainTextOutput = $config['plain-text-output']; // Display milestone titles as plain text

if ($milestone != 0 && $title != '') {
    fwrite(STDERR, sprintf(
        'You can\'t specify both a milestone and a milestone title.%s',
        PHP_EOL
    ));

    exit(1);
}

$client = new HttpClient($token);

if (!empty($title)) {
    $milestonePayload = getMilestoneByTitle($client, $user, $repo, $title);
} else {
    $milestonePayload = getMilestonePayload($client, $user, $repo, $milestone);
}

$uri = 'https://api.github.com/search/issues?q=' . urlencode(
        'milestone:"' . str_replace('"', '\"', $milestonePayload['title']) . '"'
        .' repo:' . $user . '/' . $repo
        . ' state:closed'
    );

$issues = array();
$error  = false;

do {
    $response = $client->send($uri);
    $json     = $response->getBody();
    $payload  = json_decode($json, true);

    if (! (is_array($payload) && isset($payload['items']))) {
        file_put_contents(
            'php://stderr',
            sprintf("Github API returned error message [%s]%s", is_object($payload) ? $payload['message'] : $json, PHP_EOL)
        );

        exit(1);
    }

    if (isset($payload['incomplete_results']) && ! isset($payload['incomplete_results'])) {
        file_put_contents(
            'php://stderr',
            sprintf("Github API returned incomplete results [%s]%s", $json, PHP_EOL)
        );

        exit(1);
    }

    foreach ($payload['items'] as $issue) {
        $issues[$issue['number']] = $issue;
    }

    $nextUri = $response->getNextLink();
    if ($nextUri) {
        $uri = $nextUri;
        continue;
    }

    break; // yay for tail recursion emulation =_=
} while (true);

echo "Total issues resolved: **" . count($issues) . "**" . PHP_EOL . PHP_EOL;

$textualIssues = [];
$usedLabels    = [];

foreach ($issues as $index => $issue) {
    $title = $issue['title'];
    if ($plainTextOutput === false) {
        $title = htmlentities($title, ENT_COMPAT, 'UTF-8');
        $title = str_replace(array ('[', ']', '_'), array ('&#91;', '&#92;', '&#95;'), $title);
    }

    $textualIssues[$index] = sprintf(
        '- [%d: %s](%s) thanks to @%s',
        $issue['number'],
        $title,
        $issue['html_url'],
        $issue['user']['login']
    );

    if ($config['group-by-labels']) {
        $labelNames = array_column($issue['labels'], 'name');
        sort($labelNames);
        $labelNames = implode(',', $labelNames);

        $usedLabels[$labelNames]         = isset($usedLabels[$labelNames]) ? $usedLabels[$labelNames] : [];
        $usedLabels[$labelNames][$index] = $index;
    }
}

ksort($textualIssues);

if ($config['group-by-labels'] === false) {
    echo implode(PHP_EOL, $textualIssues) . PHP_EOL;
    exit(0);
}

ksort($usedLabels);

foreach ($usedLabels as $label => $indexes) {
    if (trim($label) !== '') {
        echo PHP_EOL . $label . PHP_EOL;
    }

    echo implode(PHP_EOL, array_intersect_key($textualIssues, $indexes)) . PHP_EOL;
}

function getConfig()
{
    try {
        $opts = new Getopt(array(
            'help|h'              => 'Help; this usage message',
            'group-labels|g'      => 'Display the result grouped by labels',
            'config|c-s'          => 'Configuration file containing base (or all) configuration options',
            'token|t-s'           => 'GitHub API token',
            'user|u-s'            => 'GitHub user/organization name',
            'repo|r-s'            => 'GitHub repository name',
            'milestone|m-i'       => 'Milestone identifier',
            'title|v-s'           => 'Milestone title',
            'plain-text-output|o' => 'Display milestone titles as plain text',
        ));
        $opts->parse();
    } catch (Exception $e) {
        file_put_contents('php://stderr', $e->getMessage());
        exit(1);
    }

    if (isset($opts->h) || $opts->toArray() == array()) {
        file_put_contents('php://stdout', $opts->getUsageMessage());
        exit(0);
    }

    $config = array(
        'token'             => '',
        'user'              => '',
        'repo'              => '',
        'milestone'         => 0,
        'title'             => '',
        'group-by-labels'   => false,
        'plain-text-output' => false,
    );

    if (isset($opts->c)) {
        $userConfig = include $opts->c;
        if (false === $userConfig) {
            file_put_contents('php://stderr', sprintf(
                "Invalid configuration file specified ('%s')%s",
                $opts->c,
                PHP_EOL
            ));
            exit(1);
        }
        if (! is_array($userConfig)) {
            file_put_contents('php://stderr', sprintf(
                "Configuration file ('%s') did not return an array of configuration%s",
                $opts->c,
                PHP_EOL
            ));
            exit(1);
        }
        $config = array_merge($config, $userConfig);
    }

    if (isset($opts->token)) {
        $config['token'] = $opts->token;
    }

    if (isset($opts->user)) {
        $config['user'] = $opts->user;
    }

    if (isset($opts->repo)) {
        $config['repo'] = $opts->repo;
    }

    if (isset($opts->milestone)) {
        $config['milestone'] = $opts->milestone;
    }

    if (isset($opts->title)) {
        $config['title'] = $opts->title;
    }

    if (isset($opts->g)) {
        $config['group-by-labels'] = true;
    }

    if (isset($opts->o)) {
        $config['plain-text-output'] = true;
    }

    if (
        (
            empty($config['token'])
            || empty($config['user'])
            || empty($config['repo'])
        ) && (
            empty($config['milestone'])
            && empty($config['title'])
        )
    ) {
        file_put_contents('php://stderr', sprintf(
            "Some configuration is missing; please make sure each of the token, "
            . "user/organization, repo, and milestone are provided.%sReceived:%s%s%s",
            PHP_EOL,
            PHP_EOL,
            var_export($config, 1),
            PHP_EOL
        ));
        exit(1);
    }
    return $config;
}

/**
 * @param HttpClient $client
 * @param string $user
 * @param string $repo
 * @param int $milestone
 * @return mixed
 */
function getMilestonePayload($client, $user, $repo, $milestone)
{
    if ($milestone > 0) {
        $uri = "https://api.github.com/repos/$user/$repo/milestones/$milestone";

        $milestoneResponseBody = $client->send($uri)->getBody();
        $milestonePayload = json_decode($milestoneResponseBody, true);

        if (isset($milestonePayload['title'])) {
            return $milestonePayload;
        }

        // Milestone not located; report errors and potential matches
        fwrite(
            STDERR,
            sprintf(
                'Provided milestone ID [%s] does not exist: %s%s',
                $milestone,
                $milestoneResponseBody ?: 'Unknown error',
                PHP_EOL
            )
        );
    }

    reportExistingMilestones($client, $user, $repo);

    exit(1);
}

/**
 * @param HttpClient $client
 * @param string $user
 * @param string $repo
 * @param string $milestoneTitle
 * @return mixed
 */
function getMilestoneByTitle($client, $user, $repo, $milestoneTitle)
{
    $uri = "https://api.github.com/repos/$user/$repo/milestones?state=all";

    do {
        $response = $client->send($uri);

        $milestoneResponseBody = $response->getBody();
        $milestonesPayload = json_decode($milestoneResponseBody, true);

        foreach( $milestonesPayload as $milestonePayload) {
            if ($milestonePayload['title'] === $milestoneTitle) {
                return $milestonePayload;
            }
        }

        $nextUri = $response->getNextLink();
        if ($nextUri) {
            $uri = $nextUri;
            continue;
        }

        break;
    } while (true);

    fwrite(STDERR, sprintf(
        'Provided milestone title [%s] does not exist: %s%s',
        $milestoneTitle,
        $milestoneResponseBody,
        PHP_EOL
    ));

    reportExistingMilestones($client, $user, $repo);

    exit(1);
}

/**
 * @param HttpClient $client
 * @param string $user
 * @param string $repo
 * @return void
 */
function reportExistingMilestones($client, $user, $repo)
{
    $uri = "https://api.github.com/repos/$user/$repo/milestones?state=all";

    $milestoneList = [];
    do {
        $response = $client->send($uri);

        $milestonesResponseBody = $response->getBody();
        $milestonesPayload = json_decode($milestonesResponseBody, true);

        foreach ($milestonesPayload as $milestone) {
            $milestoneList[] = sprintf(
                '%s: "%s" [%s]%s',
                $milestone['number'],
                $milestone['title'],
                $milestone['state'],
                PHP_EOL
            );
        }

        $nextUri = $response->getNextLink();
        if ($nextUri) {
            $uri = $nextUri;
            continue;
        }

        break;
    } while (true);

    $milestoneList = array_slice($milestoneList, -20);
    fwrite(STDERR, sprintf('Recent milestone IDs are:%s', PHP_EOL));
    foreach ($milestoneList as $milestone) {
        fwrite(STDERR, $milestone);
    }
}
