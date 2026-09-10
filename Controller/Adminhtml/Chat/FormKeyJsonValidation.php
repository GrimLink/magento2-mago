<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\Chat;

/**
 * CSRF gate for chat controllers that receive a raw JSON body (form key travels in the body,
 * not the request params). Backend AbstractAction routes are validated through _processUrlKeys(),
 * not CsrfAwareActionInterface, so the check lives here.
 *
 * Using classes must expose readonly $formKey (FormKey) and $json (Json) and extend Backend\App\Action.
 */
trait FormKeyJsonValidation
{
    public function _processUrlKeys(): bool
    {
        if ($this->isValidFormKey()) {
            return true;
        }

        $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
        $this->getResponse()
            ->setHttpResponseCode(403)
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody($this->json->serialize(['error' => 'Invalid form key. Please reload the page and try again.']));

        return false;
    }

    private function isValidFormKey(): bool
    {
        try {
            $body = $this->json->unserialize((string)$this->getRequest()->getContent());
        } catch (\Throwable) {
            return false;
        }

        $formKey = (string)($body['form_key'] ?? '');
        return $formKey !== '' && hash_equals($this->formKey->getFormKey(), $formKey);
    }
}
