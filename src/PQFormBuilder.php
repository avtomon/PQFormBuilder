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
    protected $formConf = null;

    /**
     * HTML-объект формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected $formParent = null;

    /**
     * HTML-объект заголовка формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected $title = null;

    /**
     * HTML-объект меню формы, если есть деление на разделы полей
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected $menu = null;

    /**
     * HTML-объект формы (набора полей)
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected $form = null;

    /**
     * Имя поля содержащего значение <option>
     *
     * @var string
     */
    protected $selectValueFieldName = 'value';

    /**
     * Имя поля содержащего тектс <option>
     *
     * @var string
     */
    protected $selectTextFieldName = 'text';

    /**
     * phpQuery-объект формы
     *
     * @var null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected $document = null;

    /**
     * Класс картинки отображающей отсутствие картинок
     *
     * @var mixed|string
     */
    protected $stopImageClass = 'no-image';

    /**
     * HTML-код шаблона подсказки к полям
     *
     * @var mixed|string
     */
    protected $hintHTML = '<i class="material-icons tooltipped" data-position="right" data-delay="50" data-tooltip="I am a tooltip">info_outline</i>';

    /**
     * Индекс пути к файлу в массиве файлов,
     *
     * @var string
     */
    protected $filePathKey = 'file_path';

    /**
     * Индекс пути к постеру файла (если есть) в массиве файлов
     *
     * @var mixed|string
     */
    protected $filePosterKey = 'file_poster';

    /**
     * Индеск изначального имени файла в массиве файлов
     *
     * @var mixed|string
     */
    protected $fileNameKey = 'file_name';

    /**
     * Префикс для подгжужаемых в форму, ранее сохраненных файлов
     *
     * @var mixed|string
     */
    protected $fileNamePrefix = 'old_';

    /**
     * Конструктор
     *
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

        if (!empty($formConf['hintHTML'])) {
            $this->hintHTML = $formConf['hintHTML'];
        }

        if (!empty($formConf['filePathKey'])) {
            $this->filePathKey = $formConf['filePathKey'];
        }

        if (!empty($formConf['filePosterKey'])) {
            $this->filePosterKey = $formConf['filePosterKey'];
        }

        if (!empty($formConf['fileNameKey'])) {
            $this->fileNameKey = $formConf['fileNameKey'];
        }

        if (!empty($formConf['fileNamePrefix'])) {
            $this->fileNamePrefix = $formConf['fileNamePrefix'];
        }

        $this->formConf = &$formConf;

        $this->document = phpQuery::newDocument();
        $this->formParent = phpQuery::pq('<div>')->attr('id', 'formParent')->appendTo($this->document);

        $formConf['title']['html'] = !empty($formConf['title']['html']) ? $formConf['title']['html'] : $formConf['title']['text'];

        $this->title = phpQuery::pq('<div>')->html($formConf['title']['html'])->appendTo($this->formParent);
        self::renderAttributes($this->title, $formConf['title']);

        $this->form = phpQuery::pq('<form>')->appendTo($this->formParent);
        self::renderAttributes($this->form, array_merge($formConf['form'], ['method' => 'POST']), ['fields', 'labelafter']);

        $this->formConf['buttons'] = isset($this->formConf['buttons']) && is_array($this->formConf['buttons']) ? $this->formConf['buttons'] : [];

        if (!empty($formConf['sections']) && is_array($formConf['sections'])) {
            $this->renderSections($formConf['sections']);
        } else if (!empty($formConf['fields']) && is_array($formConf['fields'])) {
            $this->renderFields($this->form, $formConf['fields']);
            if (!empty($formConf['buttons']) && is_array($formConf['buttons'])) {
                $this->renderButtons($this->form, $formConf['buttons']);
            }
        }

        if (!empty($formConf['stopImageClass'])) {
            $this->stopImageClass = $formConf['stopImageClass'];
        }
    }

    /**
     * Добавить новые разделы в форму
     *
     * @param array $newSections - массив новых разделов
     *
     * @return PQFormBuilder
     */
    public function addSections(array $newSections): PQFormBuilder
    {
        $this->formConf['sections'] = array_merge($this->formConf['sections'], $newSections['sections']);
        foreach ($newSections['sections'] as $section) {
            $this->renderSection($section);
        }

        return $this;
    }

    /**
     * Добавить атрибуты к элементу
     *
     * @param \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery $el - элемент
     * @param array $attrs - массив атрибутов вида <имя атрибута> => <значение атрибута>
     * @param array $stopAttrs - массив имен атрибутов, которые добавлять не надо
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    protected static function renderAttributes(&$el, array $attrs, array $stopAttrs = [])
    {
        unset($attrs['html'], $attrs['text'], $attrs['value'], $attrs['hint']);
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
    protected function renderButtons($section, array $buttons)
    {
        foreach($buttons as $button) {
            $button['html'] = !empty($button['html']) ? $button['html'] : (!empty($button['text']) ? $button['text'] : '');
            $button['type'] = !empty($button['type']) ? $button['type'] : 'button';
            $buttonEl = phpQuery::pq('<button>')->appendTo($section);
            $buttonEl->html($button['html']);
            self::renderAttributes($buttonEl, $button);
        }

        return $section;
    }

    /**
     * Софрмировать из массива свойств раздела HTML-раздел
     *
     * @param array $section - массив свойств раздела формы
     *
     * @return array|null
     *
     * @throws \Exception
     */
    protected function renderSection(array $section)
    {
        $section['html'] = !empty($section['html']) ? $section['html'] : (!empty($section['text']) ? $section['text'] : '');
        if (empty($section['id']) || !$section['html'] || empty($section['fields']) || !is_array($section['fields'])) {
            return null;
        }

        $formSection = phpQuery::pq('<section>')->attr('id', $section['id'])->appendTo($this->form);

        $section['href'] = "#{$section['id']}";
        $menuEl = phpQuery::pq('<a>')->html($section['html'])->appendTo($this->menu);
        self::renderAttributes($menuEl, $section);

        $this->renderFields($formSection, $section['fields']);

        if (!empty($section['buttons']) && is_array($section['buttons'])) {
            $section['buttons'] = isset($section['buttons']) && is_array($section['buttons']) ? $section['buttons'] : [];
            $this->renderButtons($formSection, array_merge($this->formConf['buttons'], $section['buttons']));
        }

        return $section;
    }

    /**
     * Добавить разделы полей и их поля к форме
     *
     * @param array $sections - массив разделов
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    protected function renderSections(array $sections)
    {
        $this->menu = phpQuery::pq('<menu>');
        self::renderAttributes($this->menu, $this->formConf['menu']);
        $this->title->after($this->menu);

        foreach($sections as $number => $section) {
            $this->renderSection($section);
        }

        if (!empty($this->formConf['invisibleClass']) && !empty($this->formConf['currentClass'])) {
            $visibleNumber = !empty($this->formConf['currentNumber']) ? $this->formConf['currentNumber'] : 0;
            $this->form->find('section')->eq($visibleNumber)->siblings('section')->addClass($this->formConf['invisibleClass']);
            $this->menu->find('a')->eq($visibleNumber)->addClass($this->formConf['currentClass']);
        }

        return $this;
    }

    /**
     * Сформировать подсказку в полю формы
     *
     * @param array $field - массив свойст поля формы
     *
     * @return array|null|\phpQueryObject
     *
     * @throws \Exception
     */
    protected function renderFieldHint(array &$field)
    {
        if (empty($field['hint'])) {
            return null;
        }

        return phpQuery::pq($this->hintHTML)->attr('data-tooltip', $field['hint']);
    }

    /**
     * Добавить поля к форме
     *
     * @param $section - в какой раздел вставляем
     * @param array $fields - поля
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery|null
     */
    protected function renderFields($section, array $fields)
    {
        foreach($fields as $field) {
            if (empty($field['name']) || (empty($field['type']) && empty($field['template']))) {
                continue;
            }

            $field['html'] = !empty($field['html']) ? $field['html'] : (!empty($field['text']) ? $field['text'] : $field['name']);
            $field['id'] = !empty($field['id']) ? $field['id'] : $field['name'];
            $field['value'] = isset($field['value']) ? $field['value'] : '';


            if (!empty($this->formConf['templatePath']) && !empty($field['template'])) {
                $newElArray = $this->renderTemplateField($field);
            } else {
                $newElArray = $this->renderInlineField($field);
            }

            foreach ($newElArray as $el) {
                $section->append($el);
            }
        }

        return $section;
    }

    /**
     * Встроить в форму поле загружаемое в виде HTML-шаблона
     *
     * @param array $field - свойства поля
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function renderTemplateField(array &$field): array
    {
        if (empty($this->formConf['templatePath']) || empty($field['template'])) {
            return [];
        }

        $fieldWrapper = phpQuery::pq(file_get_contents($this->formConf['templatePath'] . '/' . $field['template']));
        $fieldWrapper->find('label, .label')->text($field['html']);
        $fieldEl = $fieldWrapper->find('input, select, textarea');
        $hint = $this->renderFieldHint($field);
        $fieldEl->after($hint);
        self::renderAttributes($fieldEl, $field);
        $fieldWrapper->find('*[data-view]')->attr('data-view', $field['name']);

        return [$fieldWrapper];
    }

    /**
     * Встроить в форму поле описанное только массивом свойств
     *
     * @param array $field - массив свойств поля
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function renderInlineField(array &$field): array
    {
        $label = phpQuery::pq('<label>')
            ->html($field['html'])
            ->attr('for', $field['id']);

        switch ($field['type']) {
            case 'select':
                $fieldEl = phpQuery::pq('<select>')->val($field['value']);
                if (!empty($field['empty-text'])) {
                    $field['options'] = $field['options'] ?? [];
                    array_unshift($field['options'], ['text' => $field['empty-text'], 'value' => '']);
                }

                self::renderAttributes($fieldEl, $field, ['type', 'options', 'empty-text']);

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
                        ->val($option['value'])
                        ->appendTo($fieldEl);
                    self::renderAttributes($optionEl, $option);
                }

                break;

            case 'textarea':
                $fieldEl = phpQuery::pq('<textarea>')->val($field['value']);
                self::renderAttributes($fieldEl, $field, ['type']);
                break;

            case 'radio':
                if (empty($field['variants'])) {
                    $fieldEl = phpQuery::pq('<input>');
                    self::renderAttributes($fieldEl, $field);
                    break;
                }

                foreach ($field['variants'] as $value) {
                    $fieldEl = phpQuery::pq('<input>')->val($value);
                    self::renderAttributes($fieldEl, $field);
                }

                break;

            default:
                $fieldEl = phpQuery::pq('<input>')->val($field['value']);
                self::renderAttributes($fieldEl, $field);
        }

        $hint = self::renderFieldHint($field);

        if ($this->formConf['labelAfter'])
            $elements = [&$fieldEl, &$hint, &$label];
        else {
            $elements = [&$label, &$fieldEl, &$hint];
        }

        if (empty($field['fieldWrapper'])) {
            return $elements;
        }

        $fieldWrapper = phpQuery::pq('<div>')->appendTo($this->form);
        self::renderAttributes($fieldWrapper, $field['fieldWrapper'] + ['required' => $field['required'] ?? '']);

        foreach ($elements as $el) {
            $fieldWrapper->append($el);
        }

        return [$fieldWrapper];
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
            //self::setInputValue($name, $value);
            if ((string) ($element = $this->form->find("[name='$name']:not([type=file]"))) {
                $element->val($value);
                continue;
            }

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
    protected function setInputValue(string $name, $value)
    {
        if (!((string) $element = $this->form->find("[name='$name']"))) {
            return null;
        }

        $element->val($value);

        return $element;
    }

    /**
     * Формирование опций и занчений выпадающего списка
     *
     * @param string $name - имя элемента формы
     * @param array $value - значение
     *
     * @return null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     * @throws \Exception
     */
    protected function setSelectValue(string $name, array $options)
    {
        if (!((string) $element = $this->form->find("select[name='$name']"))) {
            return null;
        }

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
    }

    /**
     * Вставка изображений
     *
     * @param string $name - имя элемента для отображения картинки
     * @param $value - изображение или массив изображений
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected function setImageValue(string $name, $value)
    {
        if (!((string) $element = $this->form->find("img[data-view='$name']"))) {
            return;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $element->removeClass($this->stopImageClass);
        $lastIndex = count($value) - 1;
        foreach ($value as $index => $imgSrc) {
            $imgSrc = $imgSrc['file_path'] ?? $imgSrc;
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
     * Вставка изображений
     *
     * @param string $name - имя элемента для отображения картинки
     * @param $value - изображение или массив изображений
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     */
    protected function setFileValue(string $name, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        } else {
            $name = "{$name}[]";
        }

        foreach ($value as $index => $file) {
            if (empty($file[$this->filePathKey])) {
                continue;
            }

            $file[$this->filePosterKey] = $file[$this->filePosterKey] ?? '';

            $this->form->append(
                pq('<input>')
                    ->attr('type', 'hidden')
                    ->attr('name', $this->fileNamePrefix . $name)
                    ->attr('data-poster', $file[$this->filePosterKey])
                    ->attr('data-name', $file[$this->fileNameKey])
                    ->val($file[$this->filePathKey])
            );
        }

        return $element;
    }

    /**
     * Добавить опции для выпадающего списка
     *
     * @param string|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery $select - элемент списка или имя обрабатываемого списка
     * @param array $values - массив значений списка
     * @param string $emptyText - добавлять ли в начало списка пустой элемент
     * @param array $htmls - массив дочерних элементов для опций
     * @param array $attrs - массив дополнительных атрибутов для опций
     * @param $selectedValue - выбранный элемент списка
     *
     * @return null|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     * @throws \Exception
     */
    public function setSelectOptions($select, array $values, string $emptyText = null, array $htmls = [], array $attrs = [], $selectedValue = null)
    {
        if (gettype($select) === 'string') {
            $selectName = $select;
            if (!((string) $select = $this->form->find("select[name='$selectName']")) ) {
                throw new PQFormBuilderException("Выпадающего списка с имененем $selectName нет в форме");
            }
        }

        if ($emptyText) {
            array_unshift($values, NULL);
            array_unshift($htmls, $emptyText);
            if ($attrs) {
                array_unshift($attrs, $attrs[0]);
            }
        }

        foreach ($values as $index => $value) {
            $customAttrs = $attrs;
            if ($value == $selectedValue) {
                $customAttrs['selected'] = 'selected';
            }

            $option = phpQuery::pq('<option>')
                ->val($value)
                ->html(!empty($htmls[$index]) ? $htmls[$index] : $value)
                ->appendTo($select);
            self::renderAttributes($option, $customAttrs);
        }

        return $select;
    }

    /**
     * Добавить варианты radio-списка
     *
     * @param string|\phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery $radio - элемент списка или имя обрабатываемого списка
     * @param array $values - массив значений списка
     * @param array $htmls - массив дочерних элементов для вариантов
     * @param array $attrs - массив дополнительных атрибутов для вариантов
     * @param $selectedValue - выбранный элемент списка
     *
     * @return \phpQueryObject|\QueryTemplatesParse|\QueryTemplatesSource|\QueryTemplatesSourceQuery
     *
     * @throws PQFormBuilderException
     * @throws \Exception
     */
    public function setRadioVariants($radio, array $values, array $htmls = [], array $attrs = [], $selectedValue = null)
    {
        if (gettype($radio) === 'string') {
            $radioName = $radio;
            if (!((string) $radio = $this->form->find("input[type='radio'][name='$radioName']"))) {
                throw new PQFormBuilderException("Поля с имененем $radioName нет в форме");
            }
        }

        foreach (array_reverse($values) as $index => $value) {
            $customAttrs = $attrs;
            if ($value == $selectedValue) {
                $customAttrs['selected'] = 'selected';
            }

            $variant = phpQuery::pq('<option>')
                ->val($value)
                ->html(!empty($htmls[$index]) ? $htmls[$index] : $value);

            $radio->after($variant);
            self::renderAttributes($variant, $customAttrs);
        }

        $radio->remove();

        return $variant;
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
    public function __toString(): string
    {
        return $this->formParent;
    }
}