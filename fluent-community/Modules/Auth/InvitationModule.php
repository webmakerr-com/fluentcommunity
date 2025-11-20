<?php


namespace FluentCommunity\Modules\Auth;

use FluentCommunity\Modules\Auth\Classes\InvitationHandler;

class InvitationModule
{
    public function register($app)
    {
        (new InvitationHandler)->register();
        $this->initRouter($app);
    }

    private function initRouter($app)
    {
        $app->router->group(function ($router) {
            $router->prefix('invitations')
                ->namespace('\FluentCommunity\Modules\Auth\Classes')
                ->withPolicy('\FluentCommunity\Modules\Auth\Classes\InvitationPolicy')
                ->group(function ($router) {
                    $router->get('/', 'InvitationController@getInvitations');
                    $router->delete('/{invitation_id}', 'InvitationController@delete')->int('invitation_id');
                    $router->post('/', 'InvitationController@store');
                    $router->post('/link', 'InvitationController@createNewLink');
                    $router->post('/{invitation_id}/resend', 'InvitationController@resend')->int('invitation_id');
                });
        });
    }
}
