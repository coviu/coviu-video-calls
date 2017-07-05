=== Coviu Video Calls ===
Contributors: silviapfeiffer1, ben.schaaf
Tags: Coviu, video calls, webrtc, video, audio, chat, streaming, appointments, peer-to-peer
Requires at least: 3.0
Tested up to: 4.8
Stable Tag: 0.5
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Coviu video calling to your Website.

== Description ==

Coviu is a modern video collaboration platform that is built to be extensible to satisfy any remote real-time data sharing need. It's particularly useful for video consultations, e.g. for medical appointments.

Coviu can easily be integrated into other applications through its API. This Wordpress plugin is making use of the Coviu API.

This plugin allows you to add video collaboration functionality to your Wordpress Website. This includes video conferencing as well as data conferencing, which includes image sharing, pdf sharing, shared annotations, whiteboards, shared application windows.

You are able to create video appointments with a link for each participant. You will want to share the links with the participants e.g. via email so they join at the given appointment time.

Hosts are wordpress users that can schedule appointments, users can be anyone.

The host will typically enter from within your Wordpress instance. As a host enters their room, they will be notified of participants/guests trying to join their room for a video call as these guests are "knocking" to ask to enter when they go to their appointment URL.

Home page: https://www.coviu.com/

More information: https://help.coviu.com/


== Installation ==

To install the Coviu Video plugin simply:

1. Unpack the downloaded zipped file
2. Upload the "coviu-video-calls" folder to your /wp-content/plugins directory
3. Sign up for a Coviu developer account at https://coviu.com/checkout/team?plan-type=api-plan
4. Create an API key and password - you will need these for the Wordpress plugin
5. Log into Wordpress
6. Go to the "Plugins" page
7. Activate the Coviu Video Calls plugin
8. Go to the Settings page for Coviu Calls and enter your API key credentials
9. Click on the Coviu Appointments menu item and start creating your bookings

Make sure you have set up the Wordpress instance with the right timezone in the General settings.

== Frequently Asked Questions ==

= How does the video conferencing work? =

Coviu uses a new Web standard called WebRTC to set up video calls. This standard is currently natively supported in Chrome, Firefox and Opera browsers. Internet Edge and Safari support are in progress.

WebRTC leapfrogs legacy video conferencing technology through better audio and video quality, lower latency, and fully secured connections. Coviu adds additional security through its API.

Coviu also uses Websockets to do the signalling necessary to make the video call participants aware of themselves.

The call is subsequently set up directly between all the participants. All audio, video and data flows peer-to-peer directly between the participants and is securely encrypted, so you can be sure that your call is completely private.


= How do I get the API Keys for Coviu? =

Sign up for a developer account at https://coviu.com/checkout/team?plan-type=api-plan .
After you sign in, you can create API keys.
Make sure to remember the password - it cannot be retrieved via the application.
You will find pricing information associated with this API at https://coviu.com/checkout.


= Does it work on mobile devices? =

On mobile devices, Coviu works in the same browsers as on the desktop.
Coviu also offers Android and iOS applications in which the video calls can be held.
For more information, see https://coviu.com/downloads


= What is a session? =

Here are a couple of terms that we use and what they mean.

* Session: A Coviu call that occurs between two or more parties at a specified time, and has a finite duration. A Coviu call is a web (video, audio, data) call on Coviu held through either a browser, native app, or mobile. Currently sessions may have up to 5 participants.
* Participants: Users who may participate in a coviu call. We distinguish between hosts and guests.
* Hosts: A session participant who is hosting a session and who controls access to the session. Also called the session owner. In this plugin, they are registered wordpress users.
* Guest: A session participante who has been granted access to a session by the host.


= How are a session host and a session guest matched up? =

The Coviu API creates unique links to Coviu rooms where sessions can take place.
A session host is given a unique link and is able to enter the Coviu room immediately.
A session guest is given a different unique link, which only allows them to knock.
The session host has to let the session guest enter to proceed with the session.

For example, a doctor's clinic runs a wordpress site and gets an API key from Coviu. The Clinic can then set up sessions for all of their doctors. A session is created by picking a date, start and end time and associating it with a doctor. Now the session has an owner. Guest links can now be created and shared with patients.


= Holding sessions in Wordpress pages? =

If you want to hold Coviu session on Wordpress pages rather than on a coviu.com URL,
you will want to activate the embedding of call pages in wordpress pages.
You do that on the 'Coviu Calls' settings page.
After that, the links on the Appointments page go to other Wordpress pages and
you are able to theme the layout of the page that surrounds the embedded Coviu call.


= Wordpress user roles and Appointments =

If the logged in user has a 'subscriber' role only, then they will only see their own apppointments, i.e. appointments where they are one of the hosts. Also, when they create
a new appointemnt, the appointment will automatically have them as a host.
All other users when logged in see all the appointments of all hosts.
Everyone can schedule appointments for any Wordpress users.


= Why should I use Coviu? =

Coviu’s particular strength is in its focus on documents and data and its focus on keeping all the conversation and data shielded from external intrusion.

Coviu goes beyond mere video conferencing.

It allows the live sharing of documents and data and the live sharing of annotations on the rendered documents and data.

This includes the sharing of a whiteboard to allow collaborative discussion and design.

It also allows sharing of document camera input and thus the digitisation of paper documents.

Coviu is extensible for other shared data. If you have a use case that is not satisfied yet, contact support@coviu.com.

New features are being added constantly - you can always try out Coviu with a free 'me' account at http://coviu.com/.


= What shortcodes are available? =

The plugin currently provides no shortcodes.
If you need any, please contact support@coviu.com.


== Screenshots ==

1. Screenshot1.png : the admin interface of the Coviu video calls Wordpress plugin
2. Screenshot2.png : the appointments page of the Coviu video calls Wordpress plugin
3. Screenshot3.png : example video call on Coviu


== Changelog ==

= 0.1 =
* Initial version

= 0.2 =
* Updated to the new API
* Now includes appointment bookings
* Removed all shortcodes until we know better what is required
* Uses wordpress users to select hosts.

= 0.3 =
* appointment times now start at 15min interval
* added image to copy room links
* added image to delete a participant
* different wordpress user roles see different appointment lists
* ability to create wordpress custom post types as appointment rooms
* added pagination on appointment lists
* support for wordpress 4.6

= 0.4 =
* add default post template single-cvu_session.php
* add optional coviu OAuth login requirement for users
* cache API grants globally for the API key and individually for users
* update to settings pages
* support for wordpress 4.7

= 0.5 =
* fix a timezone bug
* support for wordpress 4.8

== Upgrade Notice ==

= 2.0 =
Make sure to use the new API starting at this version.

= 0.3 =
Will now work with wordpress 4.6.

= 0.4 =
Add support for a new Coviu OAuth for team users.

= 0.5 =
Fixes a timezone bug and work with wordpress 4.8.