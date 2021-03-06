<?php namespace Broco\SocialiteLogin\Auth;

use \Config;
use \Exception;
use Illuminate\Contracts\Auth\Guard;
use Laravel\Socialite\Contracts\Factory as Socialite;

class AuthenticateUser {

	private $socialite;
	private $auth;
	private $user;

	public function __construct(Socialite $socialite, Guard $auth, UserSocialiteRepository $user)
	{
		$this->socialite = $socialite;
		$this->auth = $auth;
		$this->user = $user;
	}

	public function execute($request, $listener, $provider)
	{
		if (!$request)
		{
			return $this->redirectToProvider($provider);
		}

		return $this->redirectedFromProvider($provider, $listener);
	}

	private function redirectToProvider($provider)
	{
		return $this->getAuthorizationFirst($provider);
	}

	private function redirectedFromProvider($provider, $listener)
	{
		try
		{
			$userData = $this->getSocialUser($provider);
			$accessConfig = Config::get('socialite-login.limit-access', []);
			foreach ($accessConfig as $property => $regex)
			{
				$value = object_get($userData, $property);
				if (is_string($value) and preg_match($regex, $value))
				{
					continue;
				}
				throw new Exception('Access denied.');
			}

            $user = $this->user->findOrCreateUser($provider, $userData);

            $remember = true;
            $this->auth->login($user, $remember);
        }
        catch (Exception $e)
        {
            return $listener->loginFailure($provider, $e);
        }

		return $listener->loginSuccess($user);
	}

	private function getAuthorizationFirst($provider)
	{
		return $this->socialite->driver($provider)->redirect();
	}

	private function getSocialUser($provider)
	{
		return $this->socialite->driver($provider)->user();
	}

}