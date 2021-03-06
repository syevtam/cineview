<?php
session_start();
require_once('../db/config.php');
require_once('../const/web-info.php');
require_once('../const/check_session.php');
require_once('../const/mail.php');
require_once('../vendor/stripe/init.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../mail/src/Exception.php';
require '../mail/src/PHPMailer.php';
require '../mail/src/SMTP.php';

if ($role == "user") {

  $plan_name = $_SESSION['new_plan'];
  $plancost = $_SESSION['plan_cost'];
  $plan_due = $_SESSION['plan_due'];
  $plan_curr = strtoupper($_SESSION['st_currency']);
  $purchase_date = date('d M Y');
  $red_link = '' . $_SESSION['domain'] . '/img/' . AppLogo . '';
  $cont_link = '' . $_SESSION['domain'] . '/contact/';
  $profile_page = '' . $_SESSION['domain'] . '/user/';
  $vid_size = $_SESSION['max_vid_size'];

  try {
    \Stripe\Stripe::setApiKey($_SESSION['stripe_sk']);

    $token = $_POST['stripeToken'];
    $charge = \Stripe\Charge::create([
      'amount' => $_SESSION['unit_amount'],
      'currency' => $_SESSION['st_currency'],
      'description' => $_SESSION['item_name'],
      'source' => $token,
    ]);

    $ch_id = $charge['id'];

    $call_back = \Stripe\Charge::retrieve(
      $ch_id,
      []
    );


    if ($call_back['status'] == "succeeded") {

      $payment_id = $call_back['id'];

      try {
        $conn = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset . ';collation=' . DBCollation . ';prefix=' . DBPrefix . '', DBUser, DBPass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("DELETE FROM tbl_user_plans WHERE user = ?");
        $stmt->execute([$user_id]);

        $stmt = $conn->prepare("INSERT INTO tbl_user_plans (user, plan_name, purchase_date, expire_date, max_size) VALUES (?,?,?,?,?)");
        $stmt->execute([$user_id, $plan_name, $purchase_date, $plan_due, $vid_size]);

        $stmt = $conn->prepare("INSERT INTO tbl_transactions (trans_id, user, description, ammount, currency, purchase_date) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$payment_id, $user_id, $plan_name, $plancost, $plan_curr, $purchase_date]);

        if ($_SESSION['discounted'] == "1") {
          $stmt = $conn->prepare("INSERT INTO tbl_used_coupons (coupon, user) VALUES (?,?)");
          $stmt->execute([$_SESSION['discount_code'], $user_id]);

          $stmt = $conn->prepare("UPDATE tbl_coupons SET usage_c = usage_c + 1 WHERE id = ?");
          $stmt->execute([$_SESSION['discount_code_id']]);
        }


        $mail_message = '<body class="" style="background-color: #131720;font-family: Montserrat;-webkit-font-smoothing: antialiased;font-size: 16px;line-height: 1.4;margin: 0;padding: 0;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
      <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate;mso-table-lspace: 0pt;mso-table-rspace: 0pt;width: 100%;background-color: #131720;">
        <tr>
          <td style="font-family: Montserrat;font-size: 16px;vertical-align: top;">&nbsp;</td>
          <td class="container" style="font-family: Montserrat;font-size: 16px;vertical-align: top;display: block;max-width: 580px;padding: 10px;width: 580px;margin: 0 auto !important;">
            <div class="content" style="box-sizing: border-box;display: block;margin: 0 auto;max-width: 580px;padding: 10px;">
              <table role="presentation" class="main" style="border-collapse: separate;mso-table-lspace: 0pt;mso-table-rspace: 0pt;width: 100%;background: #151f30;border-radius: 3px;color: white !important;">
                <tr>
                  <td class="wrapper" style="font-family: Montserrat;font-size: 16px;vertical-align: top;box-sizing: border-box;padding: 20px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate;mso-table-lspace: 0pt;mso-table-rspace: 0pt;width: 100%;">
                      <tr>
                        <td style="font-family: Montserrat;font-size: 16px;vertical-align: top;">
                          <img src="' . $red_link . '" style="border: none;-ms-interpolation-mode: bicubic;width:200px; height:30px;">
                          <p style="font-family: Montserrat;font-size: 14px;font-weight: normal;margin: 0;margin-bottom: 15px;color: white;">Hi ' . $rusername . ',</p>
                          <p style="font-family: Montserrat;font-size: 14px;font-weight: normal;margin: 0;margin-bottom: 15px;color: white;">Thank your for purchasing the ' . $plan_name . '! We have successfully processed your payment of ' . number_format($plancost, 2) . ' ' . $plan_curr . '.</p>
                          <p style="font-family: Montserrat;font-size: 14px;font-weight: normal;margin: 0;margin-bottom: 15px;color: white;">You can access your subscription information from your <a href="' . $profile_page . '">profile page</a>. If you have any further questions please <a href="' . $cont_link . '">Contact Us</a>.</p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              <div class="footer" style="clear: both;margin-top: 10px;text-align: center;width: 100%;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate;mso-table-lspace: 0pt;mso-table-rspace: 0pt;width: 100%;">
                  <tr>
                    <td class="content-block" style="font-family: Montserrat;font-size: 12px;vertical-align: top;padding-bottom: 10px;padding-top: 10px;color: #999999;text-align: center;">
                      <span class="apple-link" style="color: #999999;font-size: 12px;text-align: center;">' . AppName . ', All rights reserved ' . date('Y') . '</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="content-block powered-by" style="font-family: Montserrat;font-size: 12px;vertical-align: top;padding-bottom: 10px;padding-top: 10px;color: #999999;text-align: center;">
                      Powered by <a href="https://www.instagram.com/_bwiresoft/" style="color: #999999;text-decoration: none;font-size: 12px;text-align: center;">Bwiresoft</a>.
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </td>
          <td style="font-family: Montserrat;font-size: 16px;vertical-align: top;">&nbsp;</td>
        </tr>
      </table>
  </body>';
        $mail_subject = "Payment Confirmation";
        $mail = new PHPMailer;
        $mail->SMTPOptions = array(
          'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
          )
        );
        $mail->isSMTP();
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Host = $mymail_server;
        $mail->SMTPAuth = true;
        $mail->Username = $mymail_user;
        $mail->Password = $mymail_password;
        $mail->SMTPSecure = $mymail_conn;
        $mail->Port = $mymail_port;

        $mail->setFrom($mymail_user, AppName);
        $mail->addAddress($email);

        $mail->isHTML(true);

        $mail->Subject = $mail_subject;
        $mail->Body    = $mail_message;
        $mail->AltBody = $mail_message;

        if (!$mail->send()) {
        } else {
        }

        unset($_SESSION['select_plan_started']);
        unset($_SESSION['new_plan']);
        unset($_SESSION['plan_cost']);
        unset($_SESSION['plan_due']);
        unset($_SESSION['success_url']);
        unset($_SESSION['cancel_url']);
        unset($_SESSION['domain']);
        unset($_SESSION['st_currency']);
        unset($_SESSION['unit_amount']);
        unset($_SESSION['trans_name']);
        unset($_SESSION['item_id']);
        unset($_SESSION['transaction_id']);
        unset($_SESSION['item_name']);
        unset($_SESSION['unsec_trans']);
        unset($_SESSION['discounted']);
        unset($_SESSION['max_vid_size']);

        if ($_SESSION['discounted'] == "1") {
          unset($_SESSION['discount_code_id']);
          unset($_SESSION['discount_code']);
        }

        header("location:../user");
      } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
      }
    } else {
      $_SESSION['s_error'] = 'Unknown error occured';
      //header("location:../checkout");
    }
  } catch (\Stripe\Exception\CardException $e) {

    $_SESSION['s_error'] = $e->getError()->message;
    header("location:../checkout");
  } catch (\Stripe\Exception\RateLimitException $e) {
    $_SESSION['s_error'] = 'Too many requests';
    header("location:../checkout");
  } catch (\Stripe\Exception\InvalidRequestException $e) {
    $_SESSION['s_error'] = 'Invalid parameters were supplied';
    header("location:../checkout");
  } catch (\Stripe\Exception\AuthenticationException $e) {
    $_SESSION['s_error'] = 'Authentication with Stripe API failed';
    header("location:../checkout");
  } catch (\Stripe\Exception\ApiConnectionException $e) {
    $_SESSION['s_error'] = 'Network communication with Stripe failed';
    header("location:../checkout");
  } catch (\Stripe\Exception\ApiErrorException $e) {
    $_SESSION['s_error'] = 'Too many requests';
    header("location:../checkout");
  } catch (Exception $e) {
    $_SESSION['s_error'] = 'Unknown error occured';
    header("location:../checkout");
  }
} else {
  header("location:../");
}
