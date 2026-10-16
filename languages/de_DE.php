<?php
/**
 * Deutsche Texte (Sie-Form) für alles, was Partner im Onboarding sehen und was von den
 * Plugins nicht oder nur in Du-Form übersetzt wird. Einträge hier haben Vorrang.
 *
 * Aufbau: [ textdomain => [ msgid => übersetzung ] ]; mit Kontext: "kontext\004msgid".
 * Für _n()-Strings ist der Schlüssel der Singular, der Wert [ singular, plural ].
 * Ermittelt 2026-09-25 durch Protokoll aller unübersetzten Strings beim Durchklicken des Kurses.
 */
return array(
	'tutor'                         => array(
		'%d correct'                                        => '%d richtig',
		'%d incorrect'                                      => '%d falsch',
		'Attempts'                                          => 'Versuche',
		'Back to dashboard'                                 => 'Zurück zur Übersicht',
		'Close course sidebar'                              => 'Kursnavigation schließen',
		'Complete Anyway'                                   => 'Trotzdem abschließen',
		'Complete the Course'                               => 'Kurs abschließen',
		'Details'                                           => 'Details',
		'Earned Grade'                                      => 'Erreichtes Ergebnis',
		'Enter fullscreen'                                  => 'Vollbild',
		'Exit fullscreen'                                   => 'Vollbild beenden',
		'Finish Course Early?'                              => 'Kurs vorzeitig abschließen?',
		'Get Started'                                       => 'Los geht’s',
		'Go Back to Course'                                 => 'Zurück zum Kurs',
		'If you leave now, your quiz will be submitted with the answers completed so far.' => 'Wenn Sie den Test jetzt verlassen, wird er mit den bisher gegebenen Antworten eingereicht.',
		'Leave this Quiz?'                                  => 'Test verlassen?',
		'Marks'                                             => 'Punkte',
		'Newest First'                                      => 'Neueste zuerst',
		'Nicely Done!'                                      => 'Gut gemacht!',
		'Oldest First'                                      => 'Älteste zuerst',
		'Open sorting options'                              => 'Sortieroptionen öffnen',
		'Question No: %s'                                   => 'Frage: %s',
		'Quit'                                              => 'Beenden',
		'Quiz Submitted'                                    => 'Test eingereicht',
		'Quiz: %s'                                          => 'Test: %s',
		'Reading'                                           => 'Lesetext',
		'Retry Quiz'                                        => 'Test wiederholen',
		'Retry Quiz?'                                       => 'Test wiederholen?',
		'Retrying this quiz will reset your current attempt. Your answers and score from this attempt will be lost.' => 'Wenn Sie den Test wiederholen, wird Ihr aktueller Versuch zurückgesetzt. Ihre Antworten und Ihr Ergebnis aus diesem Versuch gehen dabei verloren.',
		'Skip'                                              => 'Überspringen',
		'Starting Quiz in'                                  => 'Der Test startet in',
		'Stay Here'                                         => 'Hierbleiben',
		'Times up!'                                         => 'Die Zeit ist abgelaufen!',
		'Toggle course sidebar'                             => 'Kursnavigation ein-/ausblenden',
		'Total Attempts'                                    => 'Erlaubte Versuche',
		'Video'                                             => 'Video',
		'Video - %s mins'                                   => 'Video – %s Min.',
		'Wrong Answer'                                      => 'Falsche Antwort',
		'Yes, Leave Quiz'                                   => 'Ja, Test verlassen',
		'You have not completed all required lessons and assessments. ' => 'Sie haben noch nicht alle erforderlichen Lektionen und Tests abgeschlossen. ',
		'Your Progress'                                     => 'Ihr Fortschritt',
		'Your answers are locked in. Ready to check your score?' => 'Ihre Antworten sind gespeichert. Möchten Sie Ihr Ergebnis sehen?',
		'Your quiz was abandoned automatically because time expired before you submitted it.' => 'Ihr Test wurde automatisch beendet, weil die Zeit abgelaufen ist, bevor Sie ihn eingereicht haben.',
	),

	'tutor-pro'                     => array(
		// Sperrhinweis, wenn Lektionen nacheinander freigeschaltet werden
		"You're Almost There"                               => 'Fast geschafft',
		'Continue Previous %s'                              => 'Vorherigen Schritt fortsetzen',
		'1 step remaining to unlock this %s'                => 'Noch 1 Schritt bis zur Freischaltung',
		'Please complete previous %s first'                 => 'Bitte schließen Sie zuerst den vorherigen Schritt ab',
		'Complete the previous %1$s to unlock this content and continue to <b>%2$s</b>' => 'Schließen Sie den vorherigen Schritt ab, um diesen Inhalt freizuschalten und mit <b>%2$s</b> fortzufahren.',
		'lesson'                                            => 'Lektion',
		'quiz'                                              => 'Test',

		// Zertifikat
		'Certificate'                                       => 'Zertifikat',
		'Certificate ID'                                    => 'Zertifikats-ID',
		'Certificate Locked'                                => 'Zertifikat gesperrt',
		'Certificate of completion'                         => 'Abschlusszertifikat',
		'Certificate preview'                               => 'Zertifikatsvorschau',
		'Complete the course to unlock the certificate'     => 'Schließen Sie den Kurs ab, um das Zertifikat freizuschalten',
		'Completed'                                         => 'Abgeschlossen',
		'Course'                                            => 'Kurs',
		'Course completed by'                               => 'Kurs abgeschlossen von',
		'Download Certificate'                              => 'Zertifikat herunterladen',
		'Instructor'                                        => 'Kursleitung',
		'Issued'                                            => 'Ausgestellt am',
		'Print Certificate'                                 => 'Zertifikat drucken',
		'Quick Share'                                       => 'Schnell teilen',
		'Share Certificate'                                 => 'Zertifikat teilen',
		'Share To'                                          => 'Teilen über',
		'Skills'                                            => 'Kenntnisse',
		'Student'                                           => 'Teilnehmer/in',
		'Student Name'                                      => 'Name',
		'View Certificate'                                  => 'Zertifikat anzeigen',

		// Notizen, Hinweise, Push-Benachrichtigungen
		'Answer'                                            => 'Antwort',
		'Cancel'                                            => 'Abbrechen',
		'Cmd/Ctrl +'                                        => 'Cmd/Strg +',
		'Enable Notifications'                              => 'Benachrichtigungen aktivieren',
		'Enter to Save'                                     => 'Enter zum Speichern',
		'Live Classes'                                      => 'Live-Kurse',
		'Never'                                             => 'Nie',
		'No Notes Have Been Added!'                         => 'Noch keine Notizen vorhanden.',
		'Notes'                                             => 'Notizen',
		'Okay, I Understand'                                => 'Verstanden',
		'Question'                                          => 'Frage',
		'Required'                                          => 'Pflichtfeld',
		'Save'                                              => 'Speichern',
		'Save Note'                                         => 'Notiz speichern',
		'Take Note'                                         => 'Notiz anlegen',
		'Type your note here to save for later'             => 'Notiz hier eingeben, um sie für später zu speichern',
		'Video Notes'                                       => 'Video-Notizen',
		'Want to receive push notifications for all major on-site activities?' => 'Möchten Sie Push-Benachrichtigungen zu wichtigen Aktivitäten erhalten?',

		// E-Mails (Teile, die über gettext laufen; Betreff/Text: siehe emails-Block unten)
		'Course Instructor'                                 => 'Kursleitung',
		'See Student Report'                                => 'Bericht ansehen',
	),

	'tutor-lms-certificate-builder' => array(
		'Certificate'                                       => 'Zertifikat',
		'Certificate Builder'                               => 'Zertifikats-Editor',
		'View Certificate'                                  => 'Zertifikat anzeigen',
	),

	// Magic Login bringt eine Du-Übersetzung mit – hier einheitlich in Sie-Form. Bei ungültigen Links
	// zeigt das Plugin auf wp-login.php (Passwort-Login); Partner haben kein Passwort, daher eigener Link.
	'magic-login'                   => array(
		'Please enter your username or email address. You will receive an email message to log in.' => 'Bitte tragen Sie Ihre E-Mail-Adresse ein. Sie erhalten anschließend eine E-Mail mit einem Login-Link.',
		'Please enter your username. You will receive an email message to log in.' => 'Bitte tragen Sie Ihre E-Mail-Adresse ein. Sie erhalten anschließend eine E-Mail mit einem Login-Link.',
		'Username or Email Address'                         => 'Ihre E-Mail-Adresse',
		'Send me the link'                                  => 'Login-Link senden',
		'Send me the login link'                            => 'Login-Link senden',
		'Please check your inbox for the login link. If you did not receive a login email, check your spam folder too.' => 'Wir haben Ihnen eine E-Mail mit dem Login-Link geschickt. Bitte prüfen Sie auch Ihren Spam-Ordner, falls sie nicht ankommt.',
		'Login successful! You will be redirected shortly.' => 'Anmeldung erfolgreich! Sie werden gleich weitergeleitet.',
		'Invalid magic login token. <a href="%s">Try signing in instead</a>?' => 'Dieser Login-Link ist ungültig oder abgelaufen. <a href="/partner-magic-login/">Neuen Login-Link anfordern</a>',
		'Invalid magic login token. Please try to create <a href="%s">a new login link</a>?' => 'Dieser Login-Link ist ungültig oder abgelaufen. Bitte fordern Sie <a href="/partner-magic-login/">einen neuen Login-Link</a> an.',
	),


	// Tutor-Pro-Mailvorlagen (Betreff/Überschrift/Text sind dort teils fest auf Englisch hinterlegt)
	'emails'                        => array(
		'email_to_students' => array(
			'completed_course' => array(
				'subject'       => 'Herzlichen Glückwunsch zum Abschluss: {course_name}',
				'heading'       => 'Herzlichen Glückwunsch zum Abschluss!',
				'message'       => 'Sie haben den Kurs <strong>{course_name}</strong> erfolgreich abgeschlossen. Ihr Zertifikat steht jetzt zum Download bereit.',
				'before_button' => 'Über den folgenden Button können Sie Ihr Zertifikat jederzeit herunterladen.',
				'footer_text'   => 'Bei Fragen erreichen Sie uns unter onboarding@vitarights.de.',
			),
		),
		'email_to_teachers' => array(
			'a_student_completed_course' => array(
				'subject'       => '„{student_name}“ hat den Kurs abgeschlossen',
				'heading'       => 'Kurs abgeschlossen',
				'message'       => '<strong>{student_name}</strong> hat den Kurs <strong>{course_name}</strong> erfolgreich abgeschlossen.',
				'before_button' => 'Den Stand aller Partner sehen Sie in der <a href="{site_url}/partner-einladen/">Übersicht unter „Partner einladen“</a>.',
				'footer_text'   => 'Sie können auf diese E-Mail antworten, um die Teilnehmerin bzw. den Teilnehmer zu kontaktieren.',
			),
		),
	),
);
