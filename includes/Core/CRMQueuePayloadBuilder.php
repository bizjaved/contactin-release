<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build CRM queue payloads from Message records.
 */
final class CRMQueuePayloadBuilder {
    /**
     * Build CRM queue payload from a Message entity.
     *
     * @param Message $message
     * @return array
     */
    public static function build_from_message(Message $message): array {
        $payload = [
            'message_id' => $message->id,
            'name' => $message->name,
            'salutation' => $message->salutation ?? '',
            'display_name' => NameFormatter::display($message->salutation ?? '', $message->name ?? ''),
            'email' => $message->email,
            'message' => $message->message,
            'subject' => $message->subject,
            'phone' => $message->phone ?? '',
            'intent_category' => $message->intent_category ?? '',
            'intent_confidence' => $message->intent_confidence,
        ];

        // ATTACHMENT EXTRACTION WITH DETAILED LOGGING
        if (!empty($message->attachment)) {
            Logger::info('CRM payload: extracting attachment from message', [
                'message_id' => $message->id,
                'attachment_length' => strlen($message->attachment),
                'attachment_preview' => substr($message->attachment, 0, 150),
            ]);

            $file_paths = AttachmentHelper::extract_file_paths($message->attachment);
            
            Logger::info('CRM payload: attachment paths extracted', [
                'message_id' => $message->id,
                'paths_count' => count($file_paths),
                'paths' => $file_paths,
            ]);

            $valid_paths = [];

            foreach ($file_paths as $file_path) {
                if ($file_path && AttachmentHelper::is_valid_file($file_path)) {
                    $valid_paths[] = $file_path;
                    Logger::debug('CRM payload: attachment path validated', [
                        'message_id' => $message->id,
                        'path' => $file_path,
                        'file_exists' => true,
                    ]);
                } else {
                    Logger::warning('CRM payload: attachment path validation failed', [
                        'message_id' => $message->id,
                        'path' => $file_path,
                        'file_exists' => file_exists($file_path),
                        'readable' => $file_path && is_readable($file_path),
                    ]);
                }
            }

            if (!empty($valid_paths)) {
                $payload['attachment'] = count($valid_paths) === 1 ? $valid_paths[0] : $valid_paths;
                Logger::info('CRM payload: attachment included', [
                    'message_id' => $message->id,
                    'file_count' => count($valid_paths),
                    'type' => count($valid_paths) === 1 ? 'single' : 'multiple',
                ]);
            } else {
                // Explicitly ensure attachment key is NOT present when no valid files exist
                unset($payload['attachment']);
                // Log when attachment extraction returned no valid files
                if (!empty($file_paths)) {
                    // Had paths but none were valid (likely deleted files)
                    Logger::warning('CRM payload: extracted attachment paths but files not valid', [
                        'message_id' => $message->id,
                        'extracted_count' => count($file_paths),
                        'valid_count' => 0,
                        'reason' => 'Files missing, unreadable, or permission denied',
                        'paths_attempted' => $file_paths,
                    ]);
                } else {
                    // No paths extracted at all
                    Logger::warning('CRM payload: attachment extraction returned empty', [
                        'message_id' => $message->id,
                        'attachment_data_length' => strlen($message->attachment),
                        'attachment_preview' => substr($message->attachment, 0, 100),
                        'reason' => 'JSON parse failure, URL conversion failure, or invalid format',
                    ]);
                }
            }
        } else {
            Logger::debug('CRM payload: no attachment in message', [
                'message_id' => $message->id,
            ]);
        }

        return $payload;
    }

    /**
     * Build CRM queue payload from a message ID.
     *
     * @param int $message_id
     * @return array|null
     */
    public static function build_from_message_id(int $message_id): ?array {
        if ($message_id <= 0) {
            return null;
        }

        $message = DB::instance()->get_message_by_id($message_id);
        if (!$message) {
            return null;
        }

        return self::build_from_message($message);
    }
}
