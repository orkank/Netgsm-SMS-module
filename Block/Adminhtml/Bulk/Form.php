<?php
declare(strict_types=1);

namespace IDangerous\Sms\Block\Adminhtml\Bulk;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;

class Form extends Template
{
    /**
     * @var CustomerGroupCollection
     */
    private $customerGroupCollection;

    /**
     * @param Context $context
     * @param CustomerGroupCollection $customerGroupCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerGroupCollection $customerGroupCollection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerGroupCollection = $customerGroupCollection;
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->getUrl('*/*/save');
    }

    /**
     * Get customer groups HTML
     *
     * @return string
     */
    public function getCustomerGroupsHtml(): string
    {
        $html = '<select name="customer_groups[]" class="admin__control-multiselect" multiple="multiple">';

        foreach ($this->customerGroupCollection as $group) {
            $html .= sprintf(
                '<option value="%s">%s</option>',
                $this->escapeHtmlAttr($group->getId()),
                $this->escapeHtml($group->getCustomerGroupCode())
            );
        }

        $html .= '</select>';

        return $html;
    }

    public function getCustomerVariables()
    {
        return [
            '{{customer_name}}' => __('Customer Name'),
            '{{customer_firstname}}' => __('First Name'),
            '{{customer_lastname}}' => __('Last Name'),
            '{{customer_email}}' => __('Email'),
            '{{customer_dob}}' => __('Date of Birth'),
            '{{customer_gender}}' => __('Gender'),
            '{{customer_phone}}' => __('Phone Number')
        ];
    }
}