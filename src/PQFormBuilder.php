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
     * @param array $formConf - параметры конфигурации
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

        $this->formConf = &$formConf;

        $this->formParent = phpQuery::pq('<div>')->attr('id', 'formParent');

        $formConf['title']['html'] = !empty($formConf['title']['html']) ? $formConf['title']['html'] : $formConf['title']['text'];

        $this->title = phpQuery::pq('<div>')->html($formConf['title']['html'])->appendTo($this->formParent);
        self::renderAttributes($this->title, $formConf['title']);

        $this->form = phpQuery::pq('<form>')->appendTo($this->formParent);
        self::renderAttributes($this->form, $formConf['form'] + ['method' => 'POST'], ['fields', 'labelafter']);

        if (!empty($formConf['sections']) && is_array($formConf['sections'])) {
            $this->parseSections($formConf['sections']);
        } else if (!empty($formConf['fields']) && is_array($formConf['fields'])) {
            $this->parseFields($this->form, $formConf['fields']);
        }

        if (!empty($formConf['buttons']) && is_array($formConf['buttons'])) {
            $this->parseButtons($this->form, $formConf['buttons']);
        }
    }

    /**
     * Добавить атрибуты к элементу
     *
     * @param $el - элемент
     * @param array $attrs - массив атрибутов вида <имя атрибута> => <значение атрибута>
     * @param array $stopAttrs - массив имен атрибутов, которые добавлять не надо
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    private static function renderAttributes(&$el, array $attrs, array $stopAttrs = [])
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
     * @param $section - в какой раздел вставляем
     * @param array $buttons - конфигурация кнопок
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    private function parseButtons($section, array $buttons)
    {
        foreach($buttons as $button) {
            $button['html'] = !empty($button['html']) ? $button['html'] : (!empty($button['text']) ? $button['text'] : '');
            $button['type'] = !empty($button['type']) ? $button['type'] : 'button';
            $buttonEl = phpQuery::pq('<button>')->appendTo($section);
            $buttonEl->html($button['html']);
            self::renderAttributes($buttonEl, $button);
        }

        return $this->form;
    }

    /**
     * Добавить разделы полей и их поля к форме
     *
     * @param array $sections - массив разделов
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    private function parseSections(array $sections)
    {
        $this->menu = phpQuery::pq('<menu>');
        self::renderAttributes($this->menu, $this->formConf['menu']);
        $this->title->after($this->menu);

        foreach($sections as $number => $section) {
            $section['html'] = !empty($section['html']) ? $section['html'] : (!empty($section['text']) ? $section['text'] : '');
            if (empty($section['id']) || !$section['html'] || empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            $formSection = phpQuery::pq('<section>')->attr('id', $section['id'])->appendTo($this->form);

            $section['href'] = "#{$section['id']}";
            $menuEl = phpQuery::pq('<a>')->html($section['html'])->appendTo($this->menu);
            self::renderAttributes($menuEl, $section);

            $this->parseFields($formSection, $section['fields']);

            if (!empty($section['buttons']) && is_array($section['buttons'])) {
                $this->parseButtons($formSection, $section['buttons']);
            }
        }

        if (!empty($this->formConf['invisibleClass']) && !empty($this->formConf['currentClass'])) {
            $visibleNumber = !empty($this->formConf['currentNumber']) ? $this->formConf['currentNumber'] : 0;
            $this->form->find('section')->eq($visibleNumber)->siblings('section')->addClass($this->formConf['invisibleClass']);
            $this->menu->find('a')->eq($visibleNumber)->addClass($this->formConf['currentClass']);
        }

        return $this->menu;
    }

    /**
     * Добавить поля к форме
     *
     * @param $section - в какой раздел вставляем
     * @param array $fields - поля
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    private function parseFields($section, array $fields)
    {
        foreach($fields as $field) {
            if (empty($field['name']) || empty($field['type'])) {
                continue;
            }

            $fieldWrapper = phpQuery::pq('<div>')->appendTo($this->form);
            self::renderAttributes($fieldWrapper, $field['fieldWrapper']);

            $field['html'] = !empty($field['html']) ? $field['html'] : (!empty($field['text']) ? $field['text'] : $field['name']);

            $label = phpQuery::pq('<label>')
                ->html($field['html'])
                ->attr('for', !empty($field['id']) ? $field['id'] : $field['name'])
                ->appendTo($fieldWrapper);

            $field['value'] = !empty($field['value']) ? $field['value'] : '';

            switch ($field['type']) {
                case 'select':
                    $fieldEl = phpQuery::pq('<select>')->val($field['value']);
                    self::renderAttributes($fieldEl, $field, ['type', 'options']);

                    if (empty($field['options']) || !is_array($field['options'])) {
                        break;
                    }

                    foreach($field['options'] as $option) {
                        if (empty($option['value']) && empty($option['html']) && empty($option['text'])) {
                            continue;
                        }

                        $option['html'] = !empty($option['html']) ? $option['html'] : (!empty($option['text']) ? $option['text'] : $option['value']);
                        $optionEl = phpQuery::pq('<option>')
                            ->html($option['html'])
                            ->val()
                            ->appendTo($fieldEl);
                        self::renderAttributes($optionEl, $option);
                    }
                    break;

                case 'textarea':
                    $fieldEl = phpQuery::pq('<textarea>')->val($field['value']);
                    self::renderAttributes($fieldEl, $field, ['type']);
                    break;
                default:
                    $fieldEl = phpQuery::pq('<input>')->val($field['value']);
                    self::renderAttributes($fieldEl, $field);
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
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    public function setFormValues(array $valuesObject)
    {
        foreach($valuesObject as $name => $value) {
            $this->form->find('*[name=' + $name + ']')->val($value);
        }

        return $this->form;
    }

    /**
     * Добавить опции для выпадающего списка
     *
     * @param $name - имя обрабатываемого списка
     * @param array $values - массив значений списка
     * @param array $htmls - массив дочерних элементов для опций
     * @param array $attrs - массив дополнительных атрибутов для опций
     *
     * @return null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     */
    public function setSelectOptions($name, array $values, array $htmls = [], array $attrs = [])
    {
        if (!($select = $this->form->find('*[name=' + $name + ']'))) {
            throw new PQFormBuilderException("Поля с имененем $name нет в форме");
        }

        foreach ($values as $index => $value) {
            $option = phpQuery::pq('<option>')
                ->val($value)
                ->html($htmls[$index] ?? $value)
                ->appendTo($select);
            self::renderAttributes($option, $attrs);
        }

        return $this->form;
    }

    /**
     * Установить параметр action формы
     *
     * @param string $newAction - новое значение action
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    public function setFormAction(string $newAction)
    {
        return $this->form->attr('action', $newAction);
    }

    /**
     * Вернуть форму
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    public function getFormParent()
    {
        return $this->formParent;
    }

    /**
     * Вернуть конфиг формы
     *
     * @return array
     */
    public function getFormConf(): array
    {
        return $this->formConf;
    }
}