<?php

namespace avtomon;

use phpQuery;

class PQFormBuilderException extends \Exception
{
}

class PQFormBuilder
{
    private $formConf = null; // Конфигурация формы
    private $formParent = null; // HTML-объект формы
    private $title = null; // HTML-объект заголовка формы
    private $menu = null; // HTML-объект меню формы, если есть деление на разделы полей
    private $form = null; // HTML-объект формы (набора полей)

    /**
     * @param array $formConf
     *
     * @throws PQFormBuilderException
     */
    public function __construct(array $formConf)
    {
        if (empty($formConf['form']['action'])) {
            throw new PQFormBuilderException('Не задан урл для отправки формы');
        }

        if (empty($formConf['title']['html']) && empty($formConf['title']['text'])) {
            throw new Error('Не задан заголовок формы');
        }

        $this->formConf = $formConf;

        $this->formParent = phpQuery::pq('<div>')->attr('id', 'formParent');

        $this->title = phpQuery::pq('<div>')->html($formConf['title']['html'] && !$formConf['title']['text'])->appendTo($this->formParent);
        $this->title = self::renderAttributes($this->title, $formConf['title']);

        $this->menu = phpQuery::pq('<menu>').appendTo($this->formParent);

        $this->form = phpQuery::pq('<form>')->appendTo($this->formParent);
        $this->form = self::renderAttributes($this->form, $formConf['form'] + ['method' => 'POST'], ['fields', 'labelafter']);

        if (!empty($formConf['sections']) && is_array($formConf['sections'])) {
            $this->parseSections($formConf['sections']);
        } else if (!empty($formConf['fields']) && is_array($formConf['fields'])) {
            $this->parseFields($this->form, $formConf['fields']);
        }

        if (!empty($formConf['buttons']) && is_array($formConf['buttons'])) {
            $this->parseButtons($formConf['buttons']);
        }
    }

    /**
     * Добавить атрибуты к элементу
     *
     * @param $el - элемент
     * @param array $attrs - массив атрибутов вида <имя атрибута> => <значение атрибута>
     * @param array $stopAttrs - массив имен атрибутов, которые добавлять не надо
     *
     * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
     */
    private static function renderAttributes($el, array $attrs, array $stopAttrs = [])
    {
        unset($attrs['html'], $attrs['text'], $attrs['value']);
        foreach ($attrs as $attr => $value) {
            if (!is_string($attr) || in_array($attr, $stopAttrs)) {
                continue;
            }

            $el->attr($attr, $value);
        }

        return $el;
    }

    /**
     * Добавить кнопки, заданные в конфигурации к форме
     *
     * @param array $buttons - конфигурация кнопок
     *
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery|null
     */
    private function parseButtons(array $buttons)
    {
        foreach($buttons as $button) {
            if (empty($button['type'])) {
                continue;
            }

            $button['html'] = !empty($button['html']) ? $button['html'] : (!empty($button['text']) ? $button['text'] : '');
            $buttonEl = phpQuery::pq('<button>')->appendTo($this->form);
            $buttonEl->html($button['html']);
            $buttonEl = self::renderAttributes($buttonEl, $button);
        }

        return $this->form;
    }

    /**
     * Добавить разделы полей и их поля к форме
     *
     * @param array $sections - массив разделов
     *
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery|null
     */
    private function parseSections(array $sections)
    {
        foreach($sections as $section) {
            $section['html'] = !empty($section['html']) ? $section['html'] : (!empty($section['text']) ? $section['text'] : '');
            if (empty($section['id']) || !$section['html'] || empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            $menuEl = phpQuery::pq('<li>')->html($section['html'])->appendTo($this->menu);
            $menuEl = self::renderAttributes($menuEl, $section);

            $formSection = phpQuery::pq('<section>')->attr('id', $section['id'])->appendTo($this->form);

            $this->parseFields($formSection, $section['fields']);
        }

        return $this->menu;
    }

    /**
     * Добавить поля к форме
     *
     * @param $section - в какой раздел вставляем
     * @param array $fields - поля
     *
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery|null
     */
    private function parseFields($section, array $fields)
    {
        foreach($fields as $field) {
            if (empty($field['name']) || empty($field['type'])) {
                continue;
            }

            $fieldWrapper = phpQuery::pq('<div>').appendTo($this->form);
            $fieldWrapper = self::renderAttributes($fieldWrapper, $field['fieldWrapper']);

            $field['html'] = !empty($field['html']) ? $field['html'] : (!empty($field['text']) ? $field['text'] : $field['name']);

            $label = phpQuery::pq('<label>')
                ->html($field['html'])
                ->attr('for', !empty($field['id']) ? $field['id'] : $field['name'])
                ->appendTo($fieldWrapper);

            $field['value'] = !empty($field['value']) ? $field['value'] : '';

            switch ($field['type']) {
                case 'select':
                    if (empty($field['options']) || !is_array($field['options'])) {
                        continue;
                    }

                    $fieldEl = phpQuery::pq('<select>')->val($field['value']);
                    $fieldEl = self::renderAttributes($fieldEl, $field, ['type', 'options']);

                    foreach($field['options'] as $option) {
                        if (empty($option['value']) && empty($option['html']) && empty($option['text'])) {
                            continue;
                        }

                        $option['html'] = !empty($option['html']) ? $option['html'] : (!empty($option['text']) ? $option['text'] : $option['value']);
                        $optionEl = phpQuery::pq('<option>')
                            ->html($option['html'])
                            ->val()
                            ->appendTo($fieldEl);
                        $optionEl = self::renderAttributes($optionEl, $option);
                    }
                    break;

                case 'textarea':
                    $fieldEl = phpQuery::pq('<textarea>')->val($field['value']);
                    $fieldEl = self::renderAttributes($fieldEl, $field, ['type']);
                    break;
                default:
                    $fieldEl = phpQuery::pq('<input>')->val($field['value']);
                    $fieldEl = self::renderAttributes($fieldEl, $field);
            }

            if ($this->formConf['labelAfter'])
                $fieldEl->prependTo($fieldWrapper);
            else {
                $fieldEl->appendTo($fieldWrapper);
            }

            $fieldWrapper->appendTo($section);
        }

        return $section;
    }

    /**
     * Заполняить поля форма значениями
     *
     * @param array $valuesObject - массив значений в формате <имя поля> => <значение>
     *
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery|null
     */
    public function setFormValues(array $valuesObject)
    {
        foreach($valuesObject as $name => $value) {
            $this->form->find('*[name=' + $name + ']').val($value);
        }

        return $this->form;
    }

    /**
     * Вернуть форму в виде HTML
     *
     * @return string
     */
    public function getFormHTML(): string
    {
        return $this->formParent->wrap('<div></div>')->html();
    }
}