<?php

/**
 * Mail class - mails with php mail()
 *
 * @author  Marcel Folaron <marcel.folaron@gmail.com>
 * @version 1.0
 * @license GNU/GPL, see license.txt
 */

namespace leantime\core {

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use phpmailerException;
    use leantime\core\eventhelpers;

    class mailer
    {
        use eventhelpers;

        /**
         * @access public
         * @var    string
         */
        public $cc;

        /**
         * @access public
         * @var    string
         */
        public $bcc;

        /**
         * @access public
         * @var    string
         */
        public $text = '';

        /**
         * @access public
         * @var    string
         */
        public $subject;

        /**
         * @access public
         * @var    string
         */
        public $context;
        private $mailAgent;
        private $emailDomain;
        private $language;

        private string $logo;
        private string $companyColor;
        private string $html;

        private bool $hideWrapper = false;

        /**
         * __construct - get configurations
         *
         * @access public
         * @return void
         */
        public function __construct(\leantime\core\environment $config, language $language)
        {
            if ($config->email != '') {
                $this->emailDomain = $config->email;
            } else {
                $host = $_SERVER['HTTP_HOST'] ?? "leantime";
                $this->emailDomain = "no-reply@" . $host;
            }
            //PHPMailer
            $this->mailAgent = new PHPMailer(false);

            $this->mailAgent->CharSet = 'UTF-8';                    // Ensure UTF-8 is used for emails
            //Use SMTP or php mail().
            if ($config->useSMTP === true) {
                if ($config->debug) {
                    $this->mailAgent->SMTPDebug = 4;                // ensure all aspects (connection, TLS, SMTP, etc) are covered
                    $this->mailAgent->Debugoutput = function ($str, $level) {

                        error_log($level . ' ' . $str);
                    };
                } else {
                    $this->mailAgent->SMTPDebug = 0;
                }

                $this->mailAgent->Timeout = 20;

                $this->mailAgent->isSMTP();                                      // Set mailer to use SMTP
                $this->mailAgent->Host = $config->smtpHosts;          // Specify main and backup SMTP servers

                if (isset($config->smtpAuth) && ($config->smtpAuth === true || $config->smtpAuth === false)) {
                    $this->mailAgent->SMTPAuth = $config->smtpAuth;             // Enable SMTP user/password authentication
                } else {
                    $this->mailAgent->SMTPAuth = true;
                }

                $this->mailAgent->Username = $config->smtpUsername;                 // SMTP username
                $this->mailAgent->Password = $config->smtpPassword;                           // SMTP password
                $this->mailAgent->SMTPAutoTLS = $config->smtpAutoTLS ?? true;                 // Enable TLS encryption automatically if a server supports it
                $this->mailAgent->SMTPSecure = $config->smtpSecure;                            // Enable TLS encryption, `ssl` also accepted
                $this->mailAgent->Port = $config->smtpPort;                                    // TCP port to connect to
                if (isset($config->smtpSSLNoverify) && $config->smtpSSLNoverify === true) {     //If enabled, don't verify certifcates: accept self-signed or expired certs.
                    $this->mailAgent->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];
                }
            } else {
                $this->mailAgent->isMail();
            }

            $this->logo = $_SESSION["companysettings.logoPath"] ?? "/dist/images/logo.png";
            $this->companyColor = $_SESSION["companysettings.primarycolor"] ?? "#1b75bb";

            $this->language = $language;
        }

        /**
         *
         * setContext - sets the context for the mailing
         * (used for filters & events)
         *
         * @access public
         * @param $context
         * @return void
         */
        public function setContext($context)
        {

            $this->context = $context;
        }

        /**
         *
         * setText - sets the mailtext
         *
         * @access public
         * @param  $text
         * @return void
         */
        public function setText($text)
        {

            $this->text = $text;
        }

        /**
         *
         * setHTML - set Mail html (no function yet)
         *
         * @access public
         * @param  $html
         * @return void
         */
        public function setHtml($html, $hideWrapper = false)
        {

            $this->hideWrapper = $hideWrapper;
            $this->html = $html;
        }

        /**
         * setSubject - set mail subject
         *
         * @access public
         * @param  $subject
         * @return void
         */
        public function setSubject($subject)
        {

            $this->subject = $subject;
        }

        private function dispatchMailerEvent($hookname, $payload, $additional_params = [])
        {
            $this->dispatchMailerHook('event', $hookname, $payload, $additional_params);
        }

        private function dispatchMailerFilter($hookname, $payload, $additional_params = [])
        {
            return $this->dispatchMailerHook('filter', $hookname, $payload, $additional_params);
        }

        private function dispatchMailerHook($type, $hookname, $payload, $additional_params = [])
        {

            if ($type !== 'filter' && $type !== 'event') {
                return false;
            }

            $hooks = [$hookname];

            if (!empty($this->context)) {
                $hooks[] = "$hookname.{$this->context}";
            }

            $filteredValue = null;
            foreach ($hooks as $hook) {
                if ($type == 'filter') {
                    $filteredValue = self::dispatch_filter($hook, $payload, $additional_params);
                } elseif ($type == 'event') {
                    self::dispatch_event($hook, $payload);
                }
            }

            if ($type == 'filter') {
                return $filteredValue;
            }
        }

        /**
         * sendMail - send the mail with mail()
         *
         * @access public
         * @param  array $to
         * @param  $from
         * @return void
         * @throws phpmailerException
         */
        public function sendMail(array $to, $from)
        {

            $this->dispatchMailerEvent('beforeSendMail', []);

            $to = $this->dispatchMailerFilter('sendMailTo', $to, []);
            $from = $this->dispatchMailerFilter('sendMailFrom', $from, []);

            $this->mailAgent->isHTML(true); // Set email format to HTML

            $this->mailAgent->setFrom($this->emailDomain, $from . " (Leantime)");

            $this->mailAgent->Subject = $this->subject;

            $logoParts = parse_url($this->logo);

            if (isset($logoParts['scheme'])) {
                //Logo is URL
                $inlineLogoContent = $this->logo;
            } else {
                //Logo comes from local file system
                $this->mailAgent->addEmbeddedImage(ROOT . "" . $this->logo, 'companylogo');
                $inlineLogoContent = "cid:companylogo";
            }

            if($this->hideWrapper === true) {

                $bodyTemplate = nl2br($this->html);

            }else{

                $bodyTemplate = '
                    <table width="100%" style="background:#eeeeee; padding:15px; ">
                    <tr>
                        <td align="center" valign="top">
                            <table width="600"  style="width:600px; background-color:#ffffff; border:1px solid #ccc;">
                                <tr>
                                    <td style="padding:3px 10px;">
                                        <table>
                                            <tr>
                                            <td width="150"><img alt="Logo" src="' . $inlineLogoContent . '" width="150" style="width:150px;"></td>
                                            <td></td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px; font-family:Arial; color:#666; font-size:14px; line-height:1.7;">
                                        ' . $this->language->__('email_notifications.hi') . '
                                        <br /><br />
                                        ' . nl2br($this->html) . '
                                        <br /><br />
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center">
                        ' . sprintf($this->language->__('email_notifications.unsubscribe'), BASE_URL . '/users/editOwn/') . '
                        </td>
                    </tr>
                    </table>';

            }

            $bodyTemplate = $this->dispatchMailerFilter(
                'bodyTemplate',
                $bodyTemplate,
                [
                        [
                            'companyColor' => $this->companyColor,
                            'logoUrl' => $inlineLogoContent,
                            'languageHiText' => $this->language->__('email_notifications.hi'),
                            'emailContentsHtml' => nl2br($this->html),
                            'unsubLink' => sprintf($this->language->__('email_notifications.unsubscribe'), BASE_URL . '/users/editOwn/')
                        ]
                    ]
            );

            $this->mailAgent->Body = $bodyTemplate;

            $altBody = $this->dispatchMailerFilter(
                'altBody',
                $this->text,
                []
            );

            $this->mailAgent->AltBody = $altBody;

            if (is_array($to)) {
                $to = array_unique($to);

                foreach ($to as $recip) {
                    try {
                        $this->mailAgent->addAddress($recip);
                        $this->mailAgent->send();
                    } catch (Exception $e) {
                        error_log($this->mailAgent->ErrorInfo);
                        error_log($e);
                    }

                    $this->mailAgent->clearAllRecipients();
                }
            }

            $this->dispatchMailerEvent('afterSendMail', $to);
        }
    }

}
