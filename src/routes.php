<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\App;

function filterAnimeData($data)
{
    $reduced = [];

    foreach ($data['MediaListCollection']['lists'] as $list) {
        $filteredEntries = array_filter($list['entries'], function ($entry) {
            return isset ($entry['notes']) && strlen($entry['notes']) > 200;
        });

        $output = array_map(function ($entry) {
            $newEntry = [
                'title' => isset ($entry['media']['title']['english']) ? $entry['media']['title']['english'] : (isset ($entry['media']['title']['romaji']) ? $entry['media']['title']['romaji'] : $entry['media']['title']['native']),
                'notes' => isset ($entry['notes']) ? $entry['notes'] : '',
                'note_words' => isset ($entry['notes']) ? count(array_filter(explode(' ', $entry['notes']), function ($item) {
                    return trim($item) !== '';
                })) : 0,
                'id' => (string) $entry['id'],
                'cover' => isset ($entry['media']['bannerImage']) ? $entry['media']['bannerImage'] : null,
            ];

            if (isset ($entry['completedAt']['year'])) {
                $newEntry['date'] = new DateTime("{$entry['completedAt']['year']}-{$entry['completedAt']['month']}-{$entry['completedAt']['day']}");
                $newEntry['date_string'] = $newEntry['date']->format('j F, Y');
            }

            return $newEntry;
        }, $filteredEntries);

        $reduced = array_merge($reduced, $output);
    }

    $reduced = array_values(array_unique($reduced, SORT_REGULAR));

    // Sort by date
    usort($reduced, function ($a, $b) {
        if (isset ($a['date']) && isset ($b['date'])) {
            return $b['date']->getTimestamp() - $a['date']->getTimestamp();
        }
        return 0;
    });

    return $reduced;
}

function filterSingleAnime($data)
{
    $entry = $data['MediaList'];

    $newEntry = [
        'title' => isset($entry['media']['title']['english']) ? $entry['media']['title']['english'] : (isset($entry['media']['title']['romaji']) ? $entry['media']['title']['romaji'] : $entry['media']['title']['native']),
        'notes' => isset($entry['notes']) ? $entry['notes'] : '',
        'id' => (string) $entry['id'],
        'cover' => isset($entry['media']['bannerImage']) ? $entry['media']['bannerImage'] : null,
    ];

    if (isset($entry['completedAt']['year'])) {
        $newEntry['date'] = new DateTime("{$entry['completedAt']['year']}-{$entry['completedAt']['month']}-{$entry['completedAt']['day']}");
        $newEntry['date_string'] = $newEntry['date']->format('j F, Y');
    }

    return $newEntry;
}

return function (App $app) {
    // landing page route
    $app->get('/', function (Request $request, Response $response) {
        $this->get('renderer')->setLayout('layout.php');

        $username = $this->get('session')->get('username');
        $error = array_key_exists("error", $request->getQueryParams())
            ? $request->getQueryParams()['error']
            : null;

        $query = [
            'client_id' => $_ENV['AL_CLIENT_ID'],
            'redirect_uri' => $_ENV['AL_CALLBACK'],
            'response_type' => 'code'
        ];

        $url = "https://anilist.co/api/v2/oauth/authorize?" . urldecode(http_build_query($query));

        $data = [
            'username' => $username,
            'url' => $url,
            'error' => $error
        ];

        return $this->get('renderer')->render($response, "landing.php", $data);
    });

    // handle username form
    $app->post('/', function (Request $request, Response $response) {
        $username = $request->getParsedBody()['username'] ?? null;

        // If the 'username' parameter is present, redirect to /{username} route
        if ($username) {
            return $response->withStatus(302)->withHeader('Location', '/' . $username);
        }
    });

    // logout route
    $app->get('/logout', function (Request $request, Response $response) {
        // remove from database
        $this->get('store')->deleteBy(['username', '=', $this->get('session')->get('username')]);

        // remove the session
        $this->get('session')->delete('username');

        // redirect to landing page
        return $response->withStatus(302)->withHeader('Location', '/');
    });

    // callback route
    $app->get('/oauth_callback', function (Request $request, Response $response) {
        // get the code from the query params
        $code = $request->getQueryParams()['code'] ?? null;

        // if code is not present, redirect to login
        if (!$code) {
            return $response->withStatus(302)->withHeader('Location', '/?error=' . urlencode('No code found'));
        }

        // make a request to anilist in json format
        $gzzle = $this->get('guzzle')->request('post', "https://anilist.co/api/v2/oauth/token", [
            'body' => json_encode([
                'grant_type' => 'authorization_code',
                'client_id' => $_ENV['AL_CLIENT_ID'],
                'client_secret' => $_ENV['AL_CLIENT_SECRET'],
                'redirect_uri' => $_ENV['AL_CALLBACK'],
                'code' => $code
            ])
        ]);

        // get the access token
        $access_token = json_decode($gzzle->getBody()->getContents(), true)['access_token'];

        // if access token is not present, redirect to login
        if (!$access_token) {
            return $response->withStatus(302)->withHeader('Location', '/?error=' . urlencode('No access token found'));
        }

        // build user query
        $query = json_encode(['query' => 'query { Viewer { id name } }']);

        // make a request to anilist
        $gzzle = $this->get('guzzle')->request('post', "https://graphql.anilist.co", [
            'headers' => [
                'Authorization' => "Bearer $access_token",
            ],
            'body' => $query
        ]);

        // get userdata
        $data = json_decode($gzzle->getBody()->getContents(), true)['data'];
        $username = $data['Viewer']['name'];
        $userid = $data['Viewer']['id'];

        // save the username and access token to the database
        $this->get('store')->insert(['username' => $username, 'userid' => $userid, 'access_token' => $access_token]);

        // set the session
        $this->get('session')->set('username', $username);

        // redirect to the user page
        return $response->withStatus(302)->withHeader('Location', "/$username");
    });

    // entry edit page
    $app->get('/edit/{entry_id}', function (Request $request, Response $response, $args) {
        $entry_id = $args['entry_id'];

        // get the username from the session
        $username = $this->get('session')->get('username');

        // if the username is not present, redirect to landing page
        if (!$username) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // get user credentials from the database
        $access_token = $this->get('store')->findOneBy(['username', '=', $username])['access_token'];

        // if the entry is not found, redirect to landing page
        if (!$access_token) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // build query to get the entry
        $query = 'query($id: Int) { MediaList(id: $id) { id notes completedAt { year month day } media { bannerImage title { romaji english native } } } }';
        $data_string = json_encode(['query' => $query, 'variables' => ['id' => (int) $entry_id]]);

        // make a request to anilist
        try {
            $gzzle = $this->get('guzzle')->request('post', "https://graphql.anilist.co", [
                'body' => $data_string,
            ]);
        } catch (Exception $e) {
            // this user doesn't have access to this entry or it doesn't exist
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // filter the data
        $entry = filterSingleAnime(json_decode($gzzle->getBody()->getContents(), true)['data']);

        // render the edit page
        $this->get('renderer')->setLayout('layout.php');
        return $this->get('renderer')->render($response, "edit_entry.php", ['entry' => $entry]);
    });

    // handle entry edit form
    $app->post('/edit/{entry_id}', function (Request $request, Response $response, $args) {
        $entry_id = $args['entry_id'];

        // get the username from the session
        $username = $this->get('session')->get('username');

        // if the username is not present, redirect to landing page
        if (!$username) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // get user credentials from the database
        $access_token = $this->get('store')->findOneBy(['username', '=', $username])['access_token'];

        // if the entry is not found, redirect to landing page
        if (!$access_token) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // get the note from the form
        $note = $request->getParsedBody()['note'] ?? null;

        // if the note is not present, redirect to the edit page
        if (!$note) {
            return $response->withStatus(302)->withHeader('Location
            ', "/edit/$entry_id");
        }

        // if the note is not present, redirect to the edit page
        if (!$note) {
            return $response->withStatus(302)->withHeader('Location', "/edit/$entry_id");
        }

        // build query to update the entry
        $query = 'mutation($id: Int, $note: String) { SaveMediaListEntry(id: $id, notes: $note) { id } }';
        $data_string = json_encode(['query' => $query, 'variables' => ['id' => (int) $entry_id, 'note' => $note]]);

        // make a request to anilist
        try {
            $this->get('guzzle')->request('post', "https://graphql.anilist.co", [
                'headers' => [
                    'Authorization' => "Bearer $access_token",
                ],
                'body' => $data_string,
            ]);
        } catch (Exception $e) {
            // this user doesn't have access to this entry or it doesn't exist
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        // redirect to the user page
        return $response->withStatus(302)->withHeader('Location', "/$username");

    });

    // user page
    $app->get('/{username}', function (Request $request, Response $response, $args) {
        $query = 'query($username: String, $type: MediaType) { MediaListCollection(userName: $username, type: $type) {lists {entries {id notes completedAt { year month day } media { bannerImage title { romaji english native } } } } } }';
        $data_string = json_encode(['query' => $query, 'variables' => ['username' => $args['username'], 'type' => 'ANIME']]);

        // make a request to anilist
        try {
            $gzzle = $this->get('guzzle')->request('post', "https://graphql.anilist.co", [
                'body' => $data_string,
            ]);
        } catch (Exception $e) {
            // failed means either 404 or anilist is down. but most likely 404
            return $response->withStatus(302)->withHeader('Location', '/?error=' . urlencode('User not found'));
        }

        // filter the data
        $data = filterAnimeData(json_decode($gzzle->getBody()->getContents(), true)['data']);

        // get the username from the session
        $username = $this->get('session')->get('username');

        // if the username is not present, redirect to login
        if (!$username) {
            // render the user page
            $this->get('renderer')->setLayout('layout.php');
            return $this->get('renderer')->render($response, "user.php", ['entries' => $data, 'username' => $args['username']]);
        }

        // if the username is same as the session username, show userpage with edit features
        if ($username === $args['username']) {
            // render the user page
            $this->get('renderer')->setLayout('layout.php');
            return $this->get('renderer')->render($response, "user.php", ['entries' => $data, 'username' => $args['username'], 'editable' => true]);
        }
    });
};