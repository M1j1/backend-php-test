<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));
    $twig->addGlobal('pageScripts', '');

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


/**
 * Login action
 */
$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = ? and password = ?";
        $user = $app['db']->fetchAssoc($sql, [$username, $password]);

        if ($user){
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
});


/**
 * Logout action
 */
$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


/**
 * Todo list, detail, and json display
 */
$app->get('/todo/{id}/{json_flag}', function ($id, $json_flag) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

	// if a non empty id has been supplied then load and display the todo
    if ($id){
        $sql = "SELECT * FROM todos WHERE id = ?";
        $todo = $app['db']->fetchAssoc($sql, [$id]);

		// if the 'json_flag' was set, then return the page content as an encoded json string
		if ($json_flag) {
			return json_encode($todo);
		}
		else {
			return $app['twig']->render('todo.html', [
				'todo' => $todo,
			]);
		}
    } else {		
        $sql = "SELECT * FROM todos WHERE user_id = ?";
        $todos = $app['db']->fetchAll($sql, [$user['id']]);

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
        ]);
    }
})
->value('id', null)->value('json_flag', null);


/**
 *  Toggle the isComplete flag of a todo
 */
$app->post('/todo/{id}/toggle_complete', function ($id) use ($app) {
    $response = ['status' => 'error', 'is_complete' => 0];
    if ((null === $user = $app['session']->get('user')) || empty($id)) {
        return json_encode($response);
    }

    // pull the todo
    $sql = "SELECT * FROM todos WHERE id = ?";
    $todo = $app['db']->fetchAssoc($sql, [$id]);
    if ($todo) {

        // toggle the current 'is_complete' flag
        $is_complete = ($todo['is_complete'] == 1 ? 0 : 1);

        // update the todo with the new flag
        $sql = "UPDATE todos SET is_complete = ? WHERE id = ?";
        $app['db']->executeUpdate($sql, [$is_complete, $id]);

        $response['status'] = 'success';
        $response['is_complete'] = $is_complete;
    }

    return json_encode($response);
})
->value('id', null);


/**
 * Todo addition action
 */
$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');
	
	if (empty(trim($description))) {
        $app['request']->getSession()->getFlashBag()->add('error', "<b>Error</b>: Don't forget to add a description");
	}
	else {
		$sql = "INSERT INTO todos (user_id, description) VALUES (?, ?)";
		$app['db']->executeUpdate($sql, [$user_id, $description]);

        $app['request']->getSession()->getFlashBag()->add('success', "<b>Success</b>: Your new Todo was saved successfully");
	}

    return $app->redirect('/todo');
});


/**
 * Todo deletion action
 */
$app->match('/todo/delete/{id}', function ($id) use ($app) {
    $sql = "DELETE FROM todos WHERE id = ?";
    $app['db']->executeUpdate($sql, [$id]);

    $app['request']->getSession()->getFlashBag()->add('success', "<b>Success</b>: Todo removed successfully");

    return $app->redirect('/todo');
});