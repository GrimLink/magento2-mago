<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ColorPicker extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $html = $element->getElementHtml();
        $value = $element->getEscapedValue() ?: '#F26322';

        $html .= '<script>
            require(["jquery", "domReady!"], function($) {
                var el = document.getElementById("' . $element->getHtmlId() . '");
                if (!el) return;

                var picker = document.createElement("input");
                picker.type = "color";
                picker.value = el.value || "' . $value . '";
                picker.style.cssText = "width:40px;height:34px;border:1px solid #ccc;border-radius:4px;cursor:pointer;padding:2px;margin-left:8px;vertical-align:middle";

                picker.addEventListener("input", function() {
                    el.value = this.value;
                    el.dispatchEvent(new Event("change"));
                });
                el.addEventListener("input", function() {
                    picker.value = this.value;
                });

                el.parentNode.insertBefore(picker, el.nextSibling);
                el.style.cssText = "width:120px;display:inline-block;vertical-align:middle";
            });
        </script>';

        return $html;
    }
}
