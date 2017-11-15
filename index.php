<?php

use Phalcon\Mvc\Micro;
use Phalcon\Db\Adapter\Pdo\Mysql;

require "./restclient.php";
$rc = new RestClient();

$config = require "./config_db.php";

$connection = new Mysql($config);

$subdomain = 'xy';
$remoteApiEndpoint = "http://" . $subdomain . ".riteh.hexis.hr/api/insert";
$remoteApiUpdateEndpoint = "http://" . $subdomain . ".riteh.hexis.hr/api/update";

$app = new Micro();
$app->setService('db', $connection, true);
$app->setService('restClient', $rc, true);

$app->get('/api/tweets', function () use ($app) {

  $form = new \Phalcon\Forms\Form();
  $form->setAction('/api/insert');
  $form->add(new \Phalcon\Forms\Element\Text('title'));
  $form->add(new \Phalcon\Forms\Element\Text('body'));

  $form->add(new \Phalcon\Forms\Element\Select('useEndpoint', [
    'api' => 'Remote API',
    'local' => 'Local',
  ]));

  $form->add(new \Phalcon\Forms\Element\Submit('post'));

  echo '<form action="' . $form->getAction() . '" method="POST">';
  foreach ($form as $element) {
    echo $element->render();
  }
  echo '</form>';
});

$app->post('/api/insert', function () use ($app, $remoteApiEndpoint) {

  $post = $app->request->getPost();
  if ($post) {

    if (!isset($post['title']) || !isset($post['body'])) {
      throw new \Phalcon\Exception('invalid data provided');
    }

    if (isset($post['useEndpoint']) && $post['useEndpoint'] == 'api') {
      unset($post['useEndpoint']);
      $app->getService('restClient')->post($remoteApiEndpoint, $post);
    } else {
      $response = $app->getService('db')->execute("insert into tweet (title, body, date) value (?, ?, now())",[
        $post['title'],
        $post['body'],
      ]);
      echo $app->getService('db')->lastInsertId();
    }

  }
});


$app->get('/api/tweet/{id}', function ($id) use ($connection) {
  $tweet = $connection->fetchOne("select * from tweet where id = :id ", \Phalcon\Db::FETCH_ASSOC, [
    'id' => $id,
  ]);

  if ($tweet) {
    $form = new \Phalcon\Forms\Form();
    $form->setAction('/api/update/' . $id);
    $form->add(new \Phalcon\Forms\Element\Text('title'));
    $form->get('title')->setAttribute('value', $tweet['title']);
    $form->add(new \Phalcon\Forms\Element\Text('body'));
    $form->get('body')->setAttribute('value', $tweet['body']);

    $form->add(new \Phalcon\Forms\Element\Select('useEndpoint', [
      'api' => 'Remote API',
      'local' => 'Local',
    ]));

    $form->add(new \Phalcon\Forms\Element\Submit('post'));


    echo '<form action="' . $form->getAction() . '" method="POST">';
    foreach ($form as $element) {
      echo $element->render();
    }

    echo '</form>';

  }

});

$app->post('/api/update/{id}', function ($id) use ($app, $remoteApiUpdateEndpoint) {

  $post = $app->request->getPost();
  if ($post) {

    if (!isset($post['title']) || !isset($post['body'])) {
      throw new \Phalcon\Exception('invalid data provided');
    }

    if (isset($post['useEndpoint']) && $post['useEndpoint'] == 'api') {
      unset($post['useEndpoint']);
      $app->getService('restClient')->post($remoteApiUpdateEndpoint . $id, $post);
    } else {
      $response = $app->getService('db')->update('tweet',
        [
          'title',
          'body',
        ],
        [
          $post['title'],
          $post['body']
        ],
        [
          'conditions' => 'id = ?',
          'bind' => [
            $id,
          ],
        ]
      );
    }

  }

});


$app->handle();
