<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Http\Controllers;

use CachetHQ\Cachet\Events\CustomerHasSubscribedEvent;
use CachetHQ\Cachet\Facades\Setting;
use CachetHQ\Cachet\Models\Subscriber;
use Carbon\Carbon;
use GrahamCampbell\Binput\Facades\Binput;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscribeController extends AbstractController
{
    /**
     * Show the subscribe by email page.
     *
     * @return \Illuminate\View\View
     */
    public function showSubscribe()
    {
        return View::make('subscribe', [
            'page_title' => Setting::get('app_name'),
            'aboutApp'   => Markdown::convertToHtml(Setting::get('app_about')),
        ]);
    }

    /**
     * Handle the subscribe user.
     *
     * @return \Illuminate\View\View
     */
    public function postSubscribe()
    {
        $subscriber = Subscriber::create(['email' => Binput::get('email')]);

        if (!$subscriber->isValid()) {
            return Redirect::back()->withInput(Binput::all())
                ->with('title', sprintf(
                    '<strong>%s</strong> %s',
                    trans('dashboard.notifications.whoops'),
                    trans('cachet.subscriber.email.failure')
                ))
                ->with('errors', $subscriber->getErrors());
        }

        $successMsg = sprintf(
            '<strong>%s</strong> %s',
            trans('dashboard.notifications.awesome'),
            trans('cachet.subscriber.email.subscribed')
        );

        event(new CustomerHasSubscribedEvent($subscriber));

        return Redirect::route('status-page')->with('success', $successMsg);
    }

    /**
     * Handle the verify subscriber email.
     *
     * @param string $code
     *
     * @return \Illuminate\View\View
     */
    public function getVerify($code = null)
    {
        if (is_null($code)) {
            throw new NotFoundHttpException();
        }

        $subscriber = Subscriber::where('verify_code', '=', $code)->first();

        if (!$subscriber || $subscriber->verified()) {
            return Redirect::route('status-page');
        }

        $subscriber->verified_at = Carbon::now();
        $subscriber->save();

        $successMsg = sprintf(
            '<strong>%s</strong> %s',
            trans('dashboard.notifications.awesome'),
            trans('cachet.subscriber.email.verified')
        );

        return Redirect::route('status-page')->with('success', $successMsg);
    }

    /**
     * Handle the unsubscribe.
     *
     * @param string $code
     *
     * @return \Illuminate\View\View
     */
    public function getUnsubscribe($code = null)
    {
        if (is_null($code)) {
            throw new NotFoundHttpException();
        }

        $subscriber = Subscriber::where('verify_code', '=', $code)->first();

        if (!$subscriber || !$subscriber->verified()) {
            return Redirect::route('status-page');
        }

        $subscriber->delete();

        $successMsg = sprintf(
            '<strong>%s</strong> %s',
            trans('dashboard.notifications.awesome'),
            trans('cachet.subscriber.email.unsuscribed')
        );

        return Redirect::route('status-page')->with('success', $successMsg);
    }
}
