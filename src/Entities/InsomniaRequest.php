<?php

namespace Rico\Insomnia\Entities;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use stdClass;
use Laravel\Passport\PassportServiceProvider;


/**
 * Class InsomniaRequest
 *
 * @package Rico\Insomnia\Entities
 */
class InsomniaRequest extends InsomniaEntity
{

    public const INSOMNIA_METHOD_GET = 'GET';
    public const INSOMNIA_METHOD_PUT = 'PUT';
    public const INSOMNIA_METHOD_POST = 'POST';
    public const INSOMNIA_METHOD_HEAD = 'HEAD';
    public const INSOMNIA_METHOD_PATCH = 'PATCH';
    public const INSOMNIA_METHOD_DELETE = 'DELETE';
    public const INSOMNIA_METHOD_OPTIONS = 'OPTIONS';

    /**
     * InsomniaRequest constructor.
     *
     * @param string|null $parentId
     * @param string      $name
     * @param string      $description
     * @param string      $url
     * @param string      $method
     *
     * @throws \Exception
     */
    public function __construct(?string $parentId, string $name, string $description, string $url, string $method = self::INSOMNIA_METHOD_GET)
    {
        parent::__construct($parentId, self::INSOMNIA_REQUEST, $name, $description);

        $this->properties->method = $method;
        $this->properties->url = $url;

        $this->properties->authentication = new stdClass;
        $this->properties->body = new stdClass;

        $this->properties->headers = [];
        $this->properties->parameters = [];

        $this->properties->isPrivate = false;
        $this->properties->settingEncodeUrl = true;
        $this->properties->settingRebuildPath = true;
        $this->properties->settingSendCookies = true;
        $this->properties->settingStoreCookies = true;
        $this->properties->settingFollowRedirects = "global";
        $this->properties->settingDisableRenderRequestBody = false;

        if (class_exists(PassportServiceProvider::class))
        {
            $this->createPassportAuthentication();
        }
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    private function createPassportAuthentication(): void
    {
        $passwordClient = DB::table('oauth_clients')
                            ->where('personal_access_client', '=', 0)
                            ->where('password_client', '=', 1)
                            ->first();

        if ($passwordClient === null)
        {
            throw new Exception("Password client does not exist.");
        }

        $password = uniqid();
        $user = factory(config('auth.providers.users.model'))
            ->create([
                         'email'    => uniqid() . '@example.com',
                         'password' => bcrypt($password),
                     ]);

        $this->properties->authentication->accessTokenUrl = url('/oauth/token');
        $this->properties->authentication->authorizationUrl = url('/oauth/token');
        $this->properties->authentication->clientId = $passwordClient->id;
        $this->properties->authentication->clientSecret = $passwordClient->secret;
        $this->properties->authentication->grantType = 'password';
        $this->properties->authentication->password = $password;
        $this->properties->authentication->type = 'oauth2';
        $this->properties->authentication->username = $user->email;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function setBody(string $key, $value)
    {
        $this->properties->body->mimeType = 'application/json';
        $body = json_decode($this->properties->body->text ?? '{}', true);

        Arr::set($body, $key, $value);

        $this->properties->body->text = json_encode($body, JSON_PRETTY_PRINT);
    }
}