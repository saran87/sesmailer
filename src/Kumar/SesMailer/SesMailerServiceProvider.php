<?php namespace Kumar\SesMailer;

use Illuminate\Support\ServiceProvider;

class SesMailerServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->app['sesmailer'] = $this->app->share(function($app){
            $sesClient = $this->app->make('aws')->get('ses');

            $mailer = new Mailer($app['view'], $app['events'], $sesClient);

            $mailer->setLogger($app['log'])->setQueue($app['queue']);

            $mailer->setContainer($app);

            // If a "from" address is set, we will set it on the mailer so that all mail
            // messages sent by the applications will utilize the same "from" address
            // on each one, which makes the developer's life a lot more convenient.
            $from = $app['config']['mail.from'];

            if (is_array($from) && isset($from['address']))
            {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            // Here we will determine if the mailer should be in "pretend" mode for this
            // environment, which will simply write out e-mail to the logs instead of
            // sending it over the web, which is useful for local dev environments.
            $pretend = $app['config']->get('mail.pretend', false);

            $mailer->pretend($pretend);

            return $mailer;

        });

        $this->app->bind('Kumar\SesMailer\Mailer', 'sesmailer');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('sesmailer');
	}

}
