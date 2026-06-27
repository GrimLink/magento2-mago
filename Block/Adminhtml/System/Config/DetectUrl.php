<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DetectUrl extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $detectUrl = $this->getUrl('maggy/system/detectUrl');
        $elementId = $element->getHtmlId();

        $html = $element->getElementHtml();

        $html .= '<script>
            require(["jquery", "domReady!"], function($) {
                var el = document.getElementById("' . $elementId . '");
                if (!el) return;

                el.style.display = "inline-block";
                el.style.width = "calc(100% - 120px)";
                el.style.verticalAlign = "middle";

                var btn = document.createElement("button");
                btn.type = "button";
                btn.className = "action-default scalable";
                btn.style.cssText = "margin-left:8px;vertical-align:middle;white-space:nowrap";
                btn.innerHTML = "<span>Detect URL</span>";
                el.parentNode.insertBefore(btn, el.nextSibling);

                var msg = document.createElement("div");
                msg.style.cssText = "font-size:12px;margin-top:4px";
                btn.parentNode.insertBefore(msg, btn.nextSibling);

                btn.addEventListener("click", function() {
                    btn.disabled = true;
                    btn.querySelector("span").textContent = "Detecting...";
                    msg.textContent = "";
                    msg.style.color = "";

                    fetch("' . $detectUrl . '", {
                        headers: {"X-Requested-With": "XMLHttpRequest"},
                        credentials: "same-origin"
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        btn.disabled = false;
                        btn.querySelector("span").textContent = "Detect URL";
                        if (data.success && data.url) {
                            el.value = data.url;
                            el.dispatchEvent(new Event("change"));
                            msg.style.color = "#79a12d";
                            msg.textContent = data.message || "Detected: " + data.url;
                        } else {
                            msg.style.color = "#e22626";
                            msg.textContent = data.message || "Could not detect URL";
                        }
                    })
                    .catch(function(e) {
                        btn.disabled = false;
                        btn.querySelector("span").textContent = "Detect URL";
                        msg.style.color = "#e22626";
                        msg.textContent = "Error: " + e.message;
                    });
                });
            });
        </script>';

        return $html;
    }
}
