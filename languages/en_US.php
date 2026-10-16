<?php
/**
 * Englische Texte für Nutzer mit Sprache en_US (Profil-Sprache bzw. englische Seiten/Kurse).
 * Tutor LMS und Magic Login sind im Original englisch – hier nur, was davon abweicht: eigene Texte
 * des Plugins, Mails und einheitliche Formulierungen rund um den Login-Link.
 *
 * ACHTUNG: maschinell erstellt (2026-10-11) – vor dem Livegang gegenlesen lassen.
 */
return array(
	// Datumsformat (Website-Einstellung ist deutsch: „11. Oktober 2026“)
	'date_format'           => 'F j, Y',

	'magic-login'           => array(
		'Please enter your username or email address. You will receive an email message to log in.' => 'Please enter your email address. You will then receive an email with a login link.',
		'Please enter your username. You will receive an email message to log in.' => 'Please enter your email address. You will then receive an email with a login link.',
		'Username or Email Address'                         => 'Your email address',
		'Send me the link'                                  => 'Send login link',
		'Send me the login link'                            => 'Send login link',
		'Please check your inbox for the login link. If you did not receive a login email, check your spam folder too.' => 'We have sent you an email with your login link. If it does not arrive, please also check your spam folder.',
		'Invalid magic login token. <a href="%s">Try signing in instead</a>?' => 'This login link is invalid or has expired. <a href="/login-link/">Request a new login link</a>',
		'Invalid magic login token. Please try to create <a href="%s">a new login link</a>?' => 'This login link is invalid or has expired. Please request <a href="/login-link/">a new login link</a>.',
	),

	// Eigene Texte des Plugins (deutsch im Code)
	'vitarights-onboarding' => array(
		'Nicht bestanden'        => 'Failed',
		'Sprache'                => 'Language',
		'Login'                  => 'Log in',
		'Firmensitz'             => 'Registered office',
		'Vertriebsniederlassung' => 'Sales office',
		'Kontakt'                => 'Contact',
		'Rechtliches'            => 'Legal',
		'Impressum'              => 'Legal notice',
		'Datenschutz'            => 'Privacy policy',
		'Cookie Richtlinien'     => 'Cookie policy',
		'All Rights Reserved.'   => 'All Rights Reserved.',
		'Zum Webinar'            => 'Go to the webinar',
		'Abmelden'               => 'Log out',
		'Angemeldet als %s.'     => 'Logged in as %s.',
		'%d Tag'                 => array( '%d day', '%d days' ),
		'%d Stunde'              => array( '%d hour', '%d hours' ),
		'%d Minute'              => array( '%d minute', '%d minutes' ),
	),

	// Tutor-Pro-Mailvorlagen (wie de_DE.php, Inhalt angepasst statt Tutor-Standard)
	'emails'                => array(
		'email_to_students' => array(
			'completed_course' => array(
				'subject'       => 'Congratulations on completing {course_name}',
				'heading'       => 'Congratulations on your completion!',
				'message'       => 'You have successfully completed the course <strong>{course_name}</strong>. Your certificate is now ready to download.',
				'before_button' => 'You can download your certificate at any time using the button below.',
				'footer_text'   => 'If you have any questions, please contact us at onboarding@vitarights.de.',
			),
		),
		'email_to_teachers' => array(
			'a_student_completed_course' => array(
				'subject'       => '“{student_name}” has completed the course',
				'heading'       => 'Course completed',
				'message'       => '<strong>{student_name}</strong> has successfully completed the course <strong>{course_name}</strong>.',
				'before_button' => 'You can see the status of all partners in the <a href="{site_url}/partner-einladen/">overview on the “Partner einladen” page</a>.',
				'footer_text'   => 'You can reply to this email to contact the participant.',
			),
		),
	),

	// Einladungsmail von Magic Login (übersetzt aus der deutschen Vorlage)
	'magic_login_settings'  => array(
		'email_subject' => 'Your personal Vitarights onboarding webinar',
		'login_email'   => '<h3>Welcome to Vitarights – we are delighted to have you on board!</h3>

<p>Thank you for sending us your completed Vitarights partner agreement.<br>
With your decision, you are taking a special path: you are becoming part of a strong community with a clear mission: <strong>to sustainably improve the quality of life of people and animals.</strong></p>

<p>And this is exactly where you make the difference.</p>

<p>Before we finally accept and activate your agreement, we will take one more important step together. This step ensures that you can present yourself competently, confidently and convincingly to your customers from the very beginning: professionally, well-founded and with full conviction.</p>

<h3>The Vitarights Basic Webinar</h3>

<p>To make sure you are optimally prepared, you will be given access to our <strong>Vitarights Basic Webinar</strong>.<br><br>
In <strong>6 compact video modules</strong> you will learn:</p>

<ul>
  <li>everything about the company Vitarights Innovations</li>
  <li>the most important basics for your work as a partner</li>
  <li>a clear overview of the effectiveness and concrete benefits of our premium products</li>
  <li>valuable knowledge for a confident and professional appearance</li>
  <li>the requirement for unlocking all commission levels</li>
</ul>

<p>Along the way, you will answer short self-assessment questions – so together we make sure that you are perfectly prepared.</p>

<p>Please allow about <strong>45 minutes</strong> in total for the webinar, including the questions.</p>

<h3>Important information for you:</h3>

<ul>
  <li>Your personal login link is valid for <strong>{{EXPIRES_WITH_INTERVAL}}</strong> and can be used up to <strong>{{TOKEN_VALIDITY_COUNT}}</strong> times. After that, you can request a new link on the home page at any time.</li>
  <li>After successful completion (all modules watched and all questions answered correctly), your agreement will be activated – and you will be officially unlocked for all commission levels.</li>
</ul>

<h3>Let’s get started:</h3>

<p><strong>Login link:</strong>
<a href="{{MAGIC_LINK}}" target="_blank" rel="noreferrer noopener" style="font-size:22px; font-weight:700;">
CLICK HERE
</a></p>

<strong>You will be taken directly to your onboarding webinar and can start right away with “Start Learning”.</strong>

<p>If you have any questions or need support, we are always happy to help:
<a href="mailto:onboarding@vitarights.de">onboarding@vitarights.de</a> (please do not reply to this email).</p>

<p>We wish you an inspiring start, lots of fun with the webinar – and look forward to a successful and trusting partnership!</p>

<p>Kind regards<br>
Your Vitarights team</p>',
	),
);
