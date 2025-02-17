<?php

namespace Core\Components;

use Core\Base;
use Core\Traits\ManagesErrorTrait;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Error;

class ActionMailer extends Base
{
  use ManagesErrorTrait;

  protected static $template = '';
  protected static $template_path = '';

  protected static $from_email = '';
  protected static $from_name = '';

  private static string $body = '';
  private static array $reply_to = [];
  private static array $cc = [];
  private static array $bcc = [];
  private static array $variables = [];
  private static bool $is_html = true;

  final public static function set_template(string $template, string $path = '')
  {
    static::$template = $template;

    if (!empty($path)) {
      static::$template_path = $path;
    }
  }

  final public static function set_from(string $email, string $name = '')
  {
    static::$from_email = $email;
    static::$from_name = $name;
  }

  final public static function add_reply_to(string $email, string $name = '')
  {
    static::$reply_to[$email] = $name;
  }

  final public static function add_cc(string $email, string $name = '')
  {
    static::$cc[$email] = $name;
  }

  final public static function add_bcc(string $email, string $name = '')
  {
    static::$bcc[$email] = $name;
  }

  final public static function is_html(bool $is_html = true)
  {
    static::$is_html = $is_html;
  }

  final public static function with(array $with = [])
  {
    static::$variables = $with;
  }

  final public static function send(string|array $to, string $subject): bool
  {
    $__to = $to;
    $__subject = $subject;

    $__use_smtp = static::config('mailer.USE_SMTP') ?? false;

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) throw new Error("PHPMailer not found.");
    if (!class_exists('PHPMailer\PHPMailer\SMTP')) throw new Error("PHPMailer SMTP not found.");
    if (!class_exists('PHPMailer\PHPMailer\Exception')) throw new Error("PHPMailer Exception not found.");

    $__phpmailer = new PHPMailer(true);

    try {
      if ($__use_smtp) {
        $__smtp_settings = static::config('mailer.SMTP_SETTINGS') ?? [];

        $__phpmailer->Host = $__smtp_settings['HOST'] ?? 'localhost';
        $__phpmailer->SMTPAuth = filter_var($__smtp_settings['SMTP_AUTH'] ?? false, FILTER_VALIDATE_BOOL);
        $__phpmailer->Username = $__smtp_settings['USERNAME'] ?? '';
        $__phpmailer->Password = $__smtp_settings['PASSWORD'] ?? '';
        $__phpmailer->SMTPAutoTLS = filter_var($__smtp_settings['SMTP_AUTOTLS'] ?? true, FILTER_VALIDATE_BOOL);
        $__phpmailer->Port = $__smtp_settings['PORT'] ?? 25;
      } else {
        $__phpmailer->isMail();
      }

      if (empty(static::$from_email)) throw new Error("Invalid address: from_email");
      $__phpmailer->setFrom(static::$from_email, static::$from_name);

      if (is_string($__to)) {
        $__to_emails = explode(",", $__to);
      } else {
        $__to_emails = $__to;
      }

      if (empty($__to_emails)) throw new Error("Please provide at least one recipient email address.");

      foreach ($__to_emails as $__to_email) {
        $__phpmailer->addAddress(trim($__to_email));
      }

      if (!empty(static::$reply_to)) {
        foreach (static::$reply_to as $__reply_to_email) {
          $__phpmailer->addReplyTo(trim($__reply_to_email));
        }
      }
      if (!empty(static::$cc)) {
        foreach (static::$cc as $__cc_email) {
          $__phpmailer->addCC(trim($__cc_email));
        }
      }
      if (!empty(static::$bcc)) {
        foreach (static::$bcc as $__bcc_email) {
          $__phpmailer->addBCC(trim($__bcc_email));
        }
      }

      $__variables = [];
      foreach (static::$variables as $__key => $__value) {
        $__variables[$__key] = static::encode_html($__value);
      }
      if (!empty($__variables)) extract($__variables, EXTR_PREFIX_SAME, 'template');

      $__template_name = static::$template;
      if (empty($__template_name)) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $__template_name = $backtrace[1]['function'] ?? '';
      }

      $__template_path = rtrim(static::$template_path, "/");
      if (empty($__template_path)) {
        $__template_path = str_replace("mailer", "", strtolower(implode("/", array_slice(explode("\\", get_called_class()), 2))));
      }

      $__template_file = self::$HOME_DIR . self::APP_DIR . self::VIEWS_DIR . "mailers/" . $__template_path . "/" . $__template_name . ".template.php";
      if (!file_exists($__template_file)) throw new Error("Mail template file not found: {$__template_file}");

      ob_start();
      require_once($__template_file);
      static::$body = ob_get_clean();

      $__phpmailer->isHTML(true);
      $__phpmailer->Subject = $__subject;
      $__phpmailer->Body = static::$body;

      $__phpmailer->send();
    } catch (Exception $e) {
      throw new Error("Mailer error: " . $__phpmailer->ErrorInfo);
    }

    return true;
  }

  final protected static function encode_html(mixed $data)
  {
    if (is_array($data)) {
      return array_map([get_called_class(), 'encode_html'], $data);
    } elseif (is_string($data)) {
      return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $data;
  }

  final protected static function esc_html(string $data)
  {
    return htmlspecialchars_decode($data, ENT_QUOTES | ENT_HTML5);
  }

  final protected static function partial(string $partial, array $with = [], string $path = 'partials/')
  {
    $__partial = $partial;
    $__path = rtrim($path, "/");
    $__with = $with;



    $__partial_file_directory = self::$HOME_DIR . self::APP_DIR . self::VIEWS_DIR . "mailers/" . $__path . "/";
    if (!is_dir($__partial_file_directory)) mkdir($__partial_file_directory, 0777, true);

    $__partial_file = $__partial_file_directory . $__partial . ".partial.php";
    if (!file_exists($__partial_file)) throw new Error("Partial file not found: {$__partial_file}");

    $__safe_variables = [];
    foreach ($__with as $__key => $__value) {
      $__safe_variables[$__key] = static::encode_html($__value);
    }

    if (!empty($__safe_variables)) extract($__safe_variables, EXTR_PREFIX_SAME, 'partial');

    ob_start();
    require_once($__partial_file);
    $__partial_content = ob_get_clean();

    return $__partial_content;
  }
}
