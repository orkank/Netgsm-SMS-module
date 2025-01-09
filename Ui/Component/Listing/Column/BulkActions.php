<?php
declare(strict_types=1);

namespace IDangerous\Sms\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class BulkActions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!isset($item['entity_id'])) {
                    continue;
                }

                $item[$this->getData('name')] = [
                    'detail' => [
                        'href' => $this->urlBuilder->getUrl(
                            'idangerous_sms/bulk/detail',
                            ['bulk_id' => $item['entity_id']]
                        ),
                        'label' => __('View Details')
                    ],
                    'cancel' => [
                        'href' => $this->urlBuilder->getUrl(
                            'idangerous_sms/bulk/cancel',
                            ['bulk_id' => $item['entity_id']]
                        ),
                        'label' => __('Cancel'),
                        'hidden' => $item['status'] !== 'pending',
                        'confirm' => [
                            'title' => __('Cancel Bulk SMS'),
                            'message' => __('Are you sure you want to cancel this bulk SMS?')
                        ]
                    ]
                ];
            }
        }

        return $dataSource;
    }
}