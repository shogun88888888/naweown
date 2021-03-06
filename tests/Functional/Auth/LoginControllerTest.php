<?php

namespace Tests\Functional\Auth;

use Illuminate\Auth\Events\Login;
use Naweown\Token;
use Naweown\User;
use Naweown\Events\AuthenticationLinkWasRequested;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LoginControllerTest extends TestCase
{

    use DatabaseMigrations;

    public function testPageIsUpAndRunning()
    {
        $this->get('login');

        $this->assertResponseOk();
    }

    public function testALoggedInUserCannotVisitTheLoginPage()
    {
        $this->actingAs($this->modelFactoryFor(User::class));

        $this->get('login');

        $this->assertRedirectedTo('/');
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testCannotLoginBecauseOfInvalidInput($value)
    {

        /**
         * Simulate a GET before POSTING.
         *This is to ensure stuffs like checking the path we were redirected to
         * rather than just HTTP status code
         */
        $this->get('login');

        $this->post('login', ['email' => $value]);

        $this->assertSessionHasErrors();
        $this->assertRedirectedTo('login');
    }

    public function testTokenIsSentToTheUserAfterFillingInTheFormSuccessfully()
    {
        $this->expectsEvents(AuthenticationLinkWasRequested::class);

        $user = $this->modelFactoryFor(User::class);

        $this->get('login');

        $this->post('login', ['email' => $user->email]);

        $this->assertSessionHas('link.sent');
        $this->assertRedirectedTo('login');
    }

    public function testAUserIsNotLoggedInEvenAfterASuccessfulFormSubmission()
    {
        $this->expectsEvents(AuthenticationLinkWasRequested::class);

        $user = $this->modelFactoryFor(User::class);

        $this->get('login');

        $this->post('login', ['email' => $user->email]);

        $this->assertSessionHas('link.sent');

        //Manually visit this page again.
        //If logged in, should redirect you to the profile page or something
        //else you would still be able to use this page.

        $this->get('login');
        $this->assertResponseOk();
    }

    public function testUserIsLoggedInSuccessfully()
    {
        $this->expectsEvents(Login::class);

        $user = $this->modelFactoryFor(User::class);

        $token = $user->token;

        $this->get("login/{$token->token}");

        $this->assertRedirectedTo('/');

        $this->assertEquals($user->moniker, $this->app['auth']->guard()->user()->moniker);

        //Make sure this token cannot be reused.
        $this->dontSeeInDatabase("tokens", ["token" => $token->token ]);
    }

    public function testUserCannotLoginBecauseOfAnExpiredToken()
    {
        $user = $this->modelFactoryFor(User::class);

        Token::whereUserId($user->id)
            ->update(['created_at' => \Naweown\carbon()->subMinutes(5)]);


        $this->get("login/{$user->token->token}");

        $this->assertRedirectedTo("login");
        $this->assertSessionHas("token.expired");
    }

    public function getInvalidValues()
    {
        return [
            [
                ''
            ],
            [
                'me'
            ],
            [
                'you@you'
            ],
            [
                'roo.3'
            ]
        ];
    }
}
