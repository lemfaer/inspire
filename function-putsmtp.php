<?php

/**
 * Send mail using direct SMTP connection to reciever server
 *
 * @param string $sender sender email address
 * @param array $recipients send email to (on same domain)
 * @param string $headers mail headers
 * @param string $body mail body
 *
 * @throws Exception
 */
function putSMTP($sender, array $recipients, $headers, $body)
{
    if (!empty($recipients)) {
        $email = current($recipients);
        list($account, $domain) = explode("@", $email, 2);

        if (getmxrr($domain, $mxhosts, $mxweights)) {
            $smtp = $this->getPHPMailerSMTP();
            array_multisort($mxweights, $mxhosts);
            $mxhosts = array_slice($mxhosts, 0, 10);
            $usetls = true;

            while (!empty($mxhosts)) {
                // try to connect to 25 port
                // timeout = 1
                if ($smtp->connect(current($mxhosts), 25, 1)) {
                    $success = $smtp->hello($domain);
                    $usetls &= $smtp->getServerExt("STARTTLS");

                    if ($usetls) {
                        // try to encrypt connection
                        // if cert is invalid - reconnect
                        if ($smtp->startTLS()) {
                            $success &= $smtp->hello($domain);
                        } else {
                            $usetls = false;
                            $smtp->close();
                            continue;
                        }
                    }
                }

                if (!$success) {
                    array_shift($mxhosts);
                    $usetls = true;
                    continue;
                }

                break;
            }

            if ($success) {
                $success &= $smtp->mail($sender);
                $r = array_map(array($smtp, "recipient"), $recipients);
                $success &= array_product($r);

                if ($success) {
                    if ($smtp->data($headers . $body)) {
                        $smtp->quit();
                        $smtp->close();
                        return;
                    } else {
                        throw new Exception("Can't sent message");
                    }
                } else {
                    throw new Exception("Invalid email recipient(s)");
                }
            } else {
                throw new Exception("Can't connect to server");
            }
        } else {
            throw new Exception("Wrong email domain, unable to get mx record");
        }
    } else {
        throw new Exception("No recipients");
    }
}
