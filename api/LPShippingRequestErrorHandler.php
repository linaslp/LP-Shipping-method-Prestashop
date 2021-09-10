<?php

class LPShippingRequestErrorHandler
{
    private static $instance = null;

    public function __construct()
    {
        
    }

    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new LPShippingRequestErrorHandler();
        }

        return self::$instance;
    }

    /**
     * Check if request does have success message false
     * 
     * @return bool
     */
    public function isRequestCompletedSuccessfully($result)
    {
        $instance = self::getInstance();
        if (is_array($result) && array_key_exists('success', $result) && $result['success'] == false) {
            $resultMessage = [];
            $errors = [];
            $messages = json_decode($result['message'], true);
            if (!$messages) {
                $errors[] = $result['message'];
            }
            $fieldErrors = isset($messages['fieldValidationErrors']) ? $messages['fieldValidationErrors'] : [];
            $valueErrors = isset($messages['valueValidationErrors']) ? $messages['valueValidationErrors'] : [];
            foreach ($fieldErrors as $fieldError) {
                $message = isset($fieldError['message']) ? $fieldError['message'] : (isset($fieldError['code']) ? $fieldError['code'] : '');
                $errors[] = $fieldError['field'] . ': ' . $message;
            }
            foreach ($valueErrors as $valueError) {
                $message = isset($valueError['message']) ? $valueError['message'] : (isset($valueError['code']) ? $valueError['code'] : '');
                $errors[] = $valueError['field'] . ': ' . $message;
            }

            if(isset($messages[0]) && is_array($messages[0])) {
                foreach ($messages as $message) {
                    if (isset($message['messages'])) {
                        $errorMessage = implode(',', $message['messages']);
                    } elseif (isset($message['message'])) {
                        $errorMessage = $message['message'];
                    } else {
                        continue;
                    }
                    $errors[] = $message['field'] . ': ' . $errorMessage;
                }
            }

            if (isset($messages['error_description'])) {
                $errors[] = $messages['error_description'];
            }
            $resultMessage['message'] = implode(',', $errors);
            Configuration::updateValue('LP_SHIPPING_LAST_ERROR', serialize($resultMessage));

            return false;
        }

        Configuration::updateValue('LP_SHIPPING_LAST_ERROR', ''); 
        return true;
    }


    public function getLastError() 
    {
        $err = Configuration::get('LP_SHIPPING_LAST_ERROR');
        if (!empty($err)) {
            Configuration::updateValue('LP_SHIPPING_LAST_ERROR', '');
            return unserialize($err);
        }

        return $err;
    }
    
}
