<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;

class History extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::assistant_read';

    public function __construct(
        Context $context,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        $user = $this->_auth->getUser();
        if (!$user) {
            return $result->setData(['conversations' => []]);
        }

        $conversations = $this->conversationRepository->getListByUser((int)$user->getId());

        return $result->setData(['conversations' => $conversations]);
    }
}
