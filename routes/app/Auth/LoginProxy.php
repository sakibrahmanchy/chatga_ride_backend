<?php

namespace App\Auth;

use Illuminate\Foundation\Application;
use App\User;
use PHPUnit\Framework\Exception;

class LoginProxy
{
	const REFRESH_TOKEN = 'refreshToken';

	private $apiConsumer;

	private $auth;

	private $cookie;

	private $db;

	private $request;

	private $userRepository;

	public function __construct(Application $app, User $user)
	{
		$this->user = $user;

		$this->apiConsumer = $app->make('apiconsumer');
		$this->auth = $app->make('auth');
		$this->cookie = $app->make('cookie');
		$this->db = $app->make('db');
		$this->request = $app->make('request');
	}

	/**
	 * Attempt to create an access token using user credentials
	 *
	 * @param string $email
	 * @param string $password
	 */
	public function attemptLogin($email, $password)
	{
		$user = $this->user->where('email', $email)->first();

		if (!is_null($user)) {
			return $this->proxy('password', [
				'username' => $email,
				'password' => $password
			]);
		}

		throw new Exception("Invalid Credentials");
	}

	/**
	 * Attempt to refresh the access token used a refresh token that
	 * has been saved in a cookie
	 */
	public function attemptRefresh()
	{
		$refreshToken = $this->request->cookie(self::REFRESH_TOKEN);

		return $this->proxy('refresh_token', [
			'refresh_token' => $refreshToken
		]);
	}

	/**
	 * Proxy a request to the OAuth server.
	 *
	 * @param string $grantType what type of grant type should be proxied
	 * @param array $data the data to send to the server
	 */
	public function proxy($grantType, array $data = [])
	{
		$data = array_merge($data, [
			'client_id' => env('PASSWORD_CLIENT_ID'),
			'client_secret' => env('PASSWORD_CLIENT_SECRET'),
			'grant_type' => $grantType
		]);

		$response = $this->apiConsumer->post('/oauth/token', $data);

		if (!$response->isSuccessful()) {
			throw new Exception("Invalid Credentials");
		}

		$data = json_decode($response->getContent());

		// Create a refresh token cookie
		$this->cookie->queue(
			self::REFRESH_TOKEN,
			$data->refresh_token,
			864000, // 10 days
			null,
			null,
			false,
			true // HttpOnly
		);

		return [
			'access_token' => $data->access_token,
			'expires_in' => $data->expires_in
		];
	}

	/**
	 * Logs out the user. We revoke access token and refresh token.
	 * Also instruct the client to forget the refresh cookie.
	 */
	public function logout()
	{
		$accessToken = $this->auth->user()->token();

		$refreshToken = $this->db
			->table('oauth_refresh_tokens')
			->where('access_token_id', $accessToken->id)
			->update([
				'revoked' => true
			]);

		$accessToken->revoke();

		$this->cookie->queue($this->cookie->forget(self::REFRESH_TOKEN));
	}
}