<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

use Acquia\Pingdom\PingdomApi;
use Crummy\Phlack\Bridge\Guzzle\PhlackClient;
use Crummy\Phlack\Phlack;
use Carbon\Carbon;

/**
 * Send  a giphy in response to certain words in the channel
 */
Route::post('/listen', function()
{
  $query = Input::get('trigger_word');

  if($query)
  {
      $gifs = json_decode(file_get_contents('http://api.giphy.com/v1/gifs/random?tag='.urlencode($query).'&api_key='. env('GIPHY_KEY') ));
      $gif = $gifs->data->image_url;
      $messageText = "Did someone mention *".$query."*?".PHP_EOL.$gif;
      $config =  ['username' => env('DEVTEAM_SLACK_USERNAME'), 'token' => env('DEVTEAM_SLACK_TOKEN')];
      $client = PhlackClient::factory($config);
      $phlack = new Phlack($client);
      $messageBuilder = $phlack->getMessageBuilder();
      $messageBuilder
          ->setText($messageText)
          ->setChannel( (env('DEVTEAM_SLACK_CHANNEL')) ? env('DEVTEAM_SLACK_CHANNEL') : Input::get('channel_name') )
          ->setUsername('trollbot')
          ->setIconEmoji('trollface');
      $message = $messageBuilder->create();
      $response = $phlack->send($message);
  }
});

/**
 * Pingdom down/up - test with:
 * /pingdom?message={"check":"627134","action":"assign","incidentid":"","description":"up"}
 */
Route::get('/pingdom', function()
{
    $data = Input::get('message');
    $messageData = json_decode(urldecode($data));
    if($messageData)
    {
        $pingdom = new PingdomApi(env('PINGDOM_USER'), env('PINGDOM_PASS'), env('PINGDOM_TOKEN'));
        $check = $pingdom->getCheck($messageData->check);
        $emoji = ($messageData->description == "down")? ":-1:" : ":+1:";
        $messageText = $emoji." *".$check->name ."* is ".$messageData->description;
        $config =  ['username' => env('LS_SLACK_USERNAME'), 'token' => env('LS_SLACK_TOKEN')];
        $client = PhlackClient::factory($config);
        $phlack = new Phlack($client);
        $messageBuilder = $phlack->getMessageBuilder();
        $messageBuilder
            ->setText($messageText)
            ->setChannel( (env('LS_SLACK_CHANNEL')) ? env('LS_SLACK_CHANNEL') : Input::get('channel_name') )
            ->setUsername('Pingdom')
            ->setIconEmoji('pingdom');
        $message = $messageBuilder->create();
        $response = $phlack->send($message);
    }
});

/**
 * Feed for the devteaminc.co site
 */
Route::get('latest/{latest?}', function($latest = null)
{

  $cachekey = "latest".$latest;
  if (Cache::has($cachekey))
  {
    $latest = Cache::get($cachekey);
  }
  else
  {
    $url = "https://slack.com/api/groups.history?token=". env('SLACK_FEED_TOKEN') ."&channel=". env('SLACK_FEED_CHANNEL') ."&count=10";
    if ($latest)
    {
        $url .= "&latest=".$latest;
    }
    $latest =  file_get_contents($url);
    $expiresAt = Carbon::now()->addMinutes(1);
    Cache::put($cachekey, $latest, $expiresAt);
  }
  $latest = json_decode($latest);
  return Response::json($latest)->setCallback(Input::get('callback'));
});