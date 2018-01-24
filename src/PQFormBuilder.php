<?php

namespace avtomon;

use phpQuery;

class PQFormBuilderException extends \Exception
{
}

class PQFormBuilder
{
    /**
     * Конфигурация формы
     *
     * @var array|null
     */
    private $formConf = null;

    /**
     * HTML-объект формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private $formParent = null;

    /**
     * HTML-объект заголовка формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private $title = null;

    /**
     * HTML-объект меню формы, если есть деление на разделы полей
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private $menu = null;

    /**
     * HTML-объект формы (набора полей)
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private $form = null;

    /**
     * Имя поля содержащего значение <option>
     *
     * @var string
     */
    private $selectValueFieldName = 'value';

    /**
     * Имя поля содержащего тектс <option>
     *
     * @var string
     */
    private $selectTextFieldName = 'text';

    /**
     * phpQuery-объект формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private $document = null;

    /**
     * Класс картинки отображающей отсутствие картинок
     *
     * @var mixed|string
     */
    private $stopImageClass = 'no-image';

    /**
     * @param array $formConf - параметры конфигурации
     *
     * @throws PQFormBuilderException
     */
    public function __construct(array $formConf)
    {
        if (empty($formConf['form'])) {
            throw new PQFormBuilderException('Не задана конфигурация формы');
        }

        if (empty($formConf['form']['action'])) {
            throw new PQFormBuilderException('Не задан урл для отправки формы');
        }

        if (empty($formConf['title']['html']) && empty($formConf['title']['text'])) {
            throw new Error('Не задан заголовок формы');
        }

        if (!empty($formConf['templatePath'])) {
            $formConf['templatePath'] = $_SERVER['DOCUMENT_ROOT'] . $formConf['templatePath'];
        }

        $this->formConf = &$formConf;

        $this->document = phpQuery::newDocument();
        $this->formParent = phpQuery::pq('<div>')->attr('id', 'formParent')->appendTo($this->document);

        $formConf['title']['html'] = !empty($formConf['title']['html']) ? $formConf['title']['html'] : $formConf['title']['text'];

        $this->title = phpQuery::pq('<div>')->html($formConf['title']['html'])->appendTo($this->formParent);
        self::renderAttributes($this->title, $formConf['title']);

        $this->form = phpQuery::pq('<form>')->appendTo($this->formParent);
        self::renderAttributes($this->form, $formConf['form'] + ['method' => 'POST'], ['fields', 'labelafter']);

        $this->formConf['buttons'] = isset($this->formConf['buttons']) && is_array($this->formConf['buttons']) ? $this->formConf['buttons'] : [];

        if (!empty($formConf['sections']) && is_array($formConf['sections'])) {
            $this->parseSections($formConf['sections']);
        } else if (!empty($formConf['fields']) && is_array($formConf['fields'])) {
            $this->parseFields($this->form, $formConf['fields']);
            if (!empty($formConf['buttons']) && is_array($formConf['buttons'])) {
                $this->parseButtons($this->form, $formConf['buttons']);
            }
        }

        if (!empty($formConf['stopImageClass'])) {
            $this->stopImageClass = $formConf['stopImageClass'];
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
                $section['buttons'] = isset($section['buttons']) && is_array($section['buttons']) ? $section['buttons'] : [];
                $this->parseButtons($formSection, array_merge($this->formConf['buttons'], $section['buttons']));
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
            if (empty($field['name']) || (empty($field['type']) && empty($field['template']))) {
                continue;
            }

            if (!empty($field['fieldWrapper'])) {
                $fieldWrapper = phpQuery::pq('<div>')->appendTo($this->form);
                self::renderAttributes($fieldWrapper, $field['fieldWrapper']);
                $fieldWrapper->appendTo($section);
            } else {
                $fieldWrapper = &$section;
            }

            $field['html'] = !empty($field['html']) ? $field['html'] : (!empty($field['text']) ? $field['text'] : $field['name']);
            $field['id'] = !empty($field['id']) ? $field['id'] : $field['name'];
            $field['value'] = !empty($field['value']) ? $field['value'] : '';

            if (!empty($this->formConf['templatePath']) && !empty($field['template'])) {
                $fieldEl = phpQuery::pq(file_get_contents($this->formConf['templatePath'] . '/' . $field['template']));
                $fieldEl->find('label')->text($field['html']);
                $input = $fieldEl->find('input, select, textarea');
                self::renderAttributes($input, $field);
            } else {
                $label = phpQuery::pq('<label>')
                    ->html($field['html'])
                    ->attr('for', $field['id'])
                    ->appendTo($fieldWrapper);

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
            }

            if ($this->formConf['labelAfter'])
                $fieldEl->prependTo($fieldWrapper);
            else {
                $fieldEl->appendTo($fieldWrapper);
            }
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
            self::setInputValue($name, $value);
            self::setImageValue($name, $value);
        }

        return $this->form;
    }

    /**
     * Вставка значения элемента формы
     *
     * @param string $name - имя элемента формы
     * @param $value - значение
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     */
    private function setInputValue(string $name, $value)
    {
        if (!((string) $element = $this->form->find("[name=$name]"))) {
            return;
        }

        if (is_array($value) && $element->is('select')) {
            if (!empty($this->selectTextFieldName) || !empty($this->selectValueFieldName)) {
                throw new PQFormBuilderException('В конфигурации не заданы имена полей для получения значений и текcтов выпадающего списка');
            }

            $selectTextFieldName = in_array($this->selectTextFieldName, $value[0]) ? $this->selectTextFieldName : $this->selectValueFieldName;
            $attrs = array_map(function ($item) use ($selectTextFieldName) {
                unset($item[$this->selectTextFieldName], $item[$this->selectValueFieldName]);
            }, $value);
            $this->setSelectOptions(
                $element,
                array_column($value, $this->selectValueFieldName),
                $value['addEmpty'] ?? true,
                array_column($value, $this->selectTextFieldName),
                $attrs
            );
        } else {
            $element->val($value);
        }

        return $element;
    }

    /**
     * Вставка изображений
     *
     * @param string $name - имя элемента для отображения картинки
     * @param $value - изображение или массив изображений
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    private function setImageValue(string $name, $value)
    {
        if (!((string) $element = $this->form->find("img[data-view=$name]"))) {
            return;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $element->removeClass($this->stopImageClass);
        $lastIndex = count($value) - 1;
        foreach ($value as $index => $imgSrc) {
            $element
                ->attr('src', $imgSrc)
                ->after(
                    pq('<input>')
                        ->attr('type', 'hidden')
                        ->attr('name', $name)
                        ->val($imgSrc)
                );

            if ($index < $lastIndex) {
                $newElement = $element->clone(true);
                $element->after($newElement);
                $element = $newElement;
            }
        }

        return $element;
    }

    /**
     * Добавить опции для выпадающего списка
     *
     * @param string|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery $select - элемент списка или имя обрабатываемого списка
     * @param array $values - массив значений списка
     * @param bool $addEmpty - добавлять ли в начало списка пустой элемент
     * @param array $htmls - массив дочерних элементов для опций
     * @param array $attrs - массив дополнительных атрибутов для опций
     * @param $selectedValue - выбранный элемент списка
     *
     * @return null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     * @throws \Exception
     */
    public function setSelectOptions($select, array $values, bool $addEmpty = true, array $htmls = [], array $attrs = [], $selectedValue = null)
    {
        if (gettype($select) === 'string' && !((string) $select = $this->form->find("[name=$select]"))) {
            throw new PQFormBuilderException("Поля с имененем $select нет в форме");
        }

        if ($addEmpty) {
            array_unshift($values, NULL);
            array_unshift($htmls, '');
            if ($attrs) {
                array_unshift($attrs, $attrs[0]);
            }
        }

        foreach ($values as $index => $value) {
            if ($value == $selectedValue) {
                $attrs['selected'] = 'selected';
            }

            $option = phpQuery::pq('<option>')
                ->val($value)
                ->html(!empty($htmls[$index]) ? $htmls[$index] : $value)
                ->appendTo($select);
            self::renderAttributes($option, $attrs);
        }

        return $select;
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

    /**
     * Вернуть объект в виде строки
     *
     * @return string
     */
    public function __toString():string
    {
        return $this->formParent;
    }
}