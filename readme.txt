=== Coviu Video Calls ===
Contributors: silviapfeiffer1
Tags: Coviu, video calls, webrtc, call button, video, audio, chat, streaming, collaboration, peer-to-peer
Requires at least: 3.0
Tested up to: 4.4
Stable Tag: 0.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Coviu video calling to your Website.

== Description ==

Coviu is a modern video collaboration platform that is built to be extensible to satisfy any remote real-time data sharing need.

Coviu can easily be integrated into other applications through its API. This Wordpress plugin is making use of the Coviu API.

This plugin allows you to add video collaboration functionality to your Wordpress Website. This includes video conferencing as well as data conferencing, which includes image sharing, pdf sharing, shared annotations, whiteboards, shared application windows.

You will be able to add a shortcode to your Wordpress pages that creates a button to link into a video call that you own. It also provides you with a URL to share with others that can then join your video call in a Web browser. You will be notified of guests trying to join your video call and can decide to let them join.

Home page: http://www.coviu.com/


== Installation ==

To install the Coviu Video plugin simply:

1. Unpack the downloaded zipped file
2. Upload the "coviu-video-calls" folder to your /wp-content/plugins directory
3. Get a Coviu API key from support@coviu.com
4. Log into Wordpress
5. Go to the "Plugins" page
6. Activate the Coviu Video Calls plugin by entering API key credentials
7. Add appropriate shortcodes to your Wordpress pages or posts


== Frequently Asked Questions ==

= How does the video conferencing work? =

Coviu uses a new Web standard called WebRTC to set up video calls. This standard is currently natively supported in Chrome, Firefox and Opera browsers. Internet Edge and Safari support are in progress.

WebRTC leapfrogs legacy video conferencing technology through better audio and video quality, lower latency, and fully secured connections. Coviu adds additional security through its API.

Coviu also uses Websockets to do the signalling necessary to make the video call participants aware of themselves.

The call is subsequently set up directly between all the participants. All audio, video and data flows peer-to-peer directly between the participants and is securely encrypted, so you can be sure that your call is completely private.


= How do I get the API Keys for Coviu? =

Email support@coviu.com for a set of API keys while the Coviu API is in alpha.


= Does it work on mobile devices? =

On mobile devices, Coviu works in the same browsers as on the desktop. Coviu will also offer Android and iOS applications in which the video calls can be held. Contact support@coviu.com for more information.


= What shortcodes are available? =

The plugin provides four shortcodes.

[coviu-url-owner ref='xxx' sessionid='yyy' start='time' end='time']

This creates a URL to a video conference for the owner of the video conference session.
Provide the following details:
 - identify the owner by ref
 - reference a session by id
 - provide optional start and end time

[coviu-link-owner ref='xxx' sessionid='yyy' start='time' end='time' embed='true']

Same as the coviu-url-owner, except the URL is behind a button to click on or embedded into the page.
Provide the additional detail:
 - switch between URL and iframe embedding via the embed flag


[coviu-url-guest ref='xxx' sessionid='yyy' name='patient']

This creates a URL to a video conference for a guest of the video conference session.
Provide the following details:
 - identify the owner by ref
 - identify the session by sessionid
 - provide a name for the guest

[coviu-link-guest ref='xxx' sessionid='yyy' name='patient' embed='false']

Same as the coviu-url-guest, except the URL is behind a button to click on or embedded into the page.
Privde the additional detail:
 - switch between URL and iframe embedding via the embed flag


= What is a subscription? =

Here are a couple of terms that we use and what they mean.

* Session - A web (video, audio, data) call on Coviu through either a browser, native app, or mobile. Currently sessions may have up to 5 participants.
* Subscription - A user who has arranged to have access to the system for the purposes of hosting sessions.
* Session Owner - The user of a subscription who is hosting a session and who controls access to the session.
* Session Guest - A user (person not subscribed) who has been granted access to a session by the session owner.


= How are a session owner and a session guest matched up? =

The session owner is identified through a subscription, which is added by the API user. The API user can provide a custom reference to the subscription, which identifies the session owner to them. A session is identified by its ID and a reference to a subscription and can have a start and end date and time. A session guest is associated with a session through the session ID and the subscription reference, the latter of which identifies the session owner.

For example, a doctor's clinic runs a wordpress site and gets an API key from Coviu. The Clinic can then create subscriptions for all of their doctors. These doctors can hold many sessions. A session is created by picking a session ID and associating it with a doctor (via the subscription reference). Now the session has an owner. Anyone else joining that session is a session guest.


= Why should I use Coviu? =

Coviu’s particular strength is in its focus on documents and data and its focus on keeping all the conversation and data shielded from external intrusion.

Coviu goes beyond mere video conferencing.

It allows the live sharing of documents and data and the live sharing of annotations on the rendered documents and data.

This includes the sharing of a whiteboard to allow collaborative discussion and design.

It also allows sharing of document camera input and thus the digitisation of paper documents.

Coviu is extensible for other shared data. If you have a use cas that is not satisfied yet, contact support@coviu.com.

New features are being added constantly - you can always try out Coviu with a free 'me' account at http://coviu.com/.
 

== Screenshots ==

1. Screenshot1.png : the admin interface of the Coviu video calls Wordpress extension
2. Screenshot2.png : example owner button and guest link for a Coviu call URL created via shortcode
3. Screenshot3.png : example video call for an owner created via shortcode with embed flag
4. Screenshot4.png : example video call for a guest created via shortcode with embed flag
5. Screenshot5.png : example connected video call created via shortcode with embed flag


== Changelog ==

= 0.1 =
* Initial version

