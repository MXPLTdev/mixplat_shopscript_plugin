<?php
return array(
    'api_key'          => array(
        'value'        => '',
        'title'        => 'API key',
        'placeholder'  => '',
        'description'  => '',
        'control_type' => waHtmlControl::INPUT,
    ),

    'project_id'      => array(
        'value'        => '',
        'title'        => 'Id проекта',
        'description'  => '',
        'placeholder'  => '',
        'control_type' => waHtmlControl::INPUT,

    ),
    'form_id'      => array(
        'value'        => '',
        'title'        => 'Id платежной формы',
        'description'  => '',
        'placeholder'  => '',
        'control_type' => waHtmlControl::INPUT,

    ),

    'test'   => array(
        'value'         => '1',
        'placeholder'   =>  '',
        'title'         => 'Режим',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            '1'   => 'Тестовый',
            '0'   => 'Рабочий'
            )
        ),
    'send_receipt'   => array(
            'value'         => '1',
            'placeholder'   =>  '',
            'title'         => 'Печать чека',
            'description'   => '',
            'control_type'  => waHtmlControl::SELECT,
            'options'       => array(
                '1'   => 'Да',
                '0'   => 'Нет'
                )
        ),
    'payment_method'   => array(
        'value'         => 'full_prepayment',
        'placeholder'   =>  '',
        'title'         => 'Признак способа расчёта',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            'full_prepayment' => 'полная предоплата',
            'prepayment' => 'частичная предоплата',
            'advance' => 'аванс',
            'full_payment' => 'полный расчет',
            'partial_payment' => 'частичный расчет и кредит',
            'credit' => 'кредит',
            'credit_payment' => 'выплата по кредиту',
            )
        ),
    'payment_object'   => array(
        'value'         => 'commodity',
        'placeholder'   =>  '',
        'title'         => 'Признак предмета расчёта',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            'commodity' => 'товар',
            'excise' => 'подакцизный товар',
            'job' => 'работа',
            'service' => 'услуга',
            'payment' => 'платеж',
            'property_right' => 'Передача имущественных прав',
            'composite' => 'несколько вариантов',
            'another' => 'другое',
            )
        ),
    'payment_object_delivery'   => array(
        'value'         => 'service',
        'placeholder'   =>  '',
        'title'         => 'Признак предмета расчёта на доставку',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            'commodity' => 'товар',
            'excise' => 'подакцизный товар',
            'job' => 'работа',
            'service' => 'услуга',
            'payment' => 'платеж',
            'property_right' => 'Передача имущественных прав',
            'composite' => 'несколько вариантов',
            'another' => 'другое',
            )
        ),
    'payment_scheme'   => array(
        'value'         => 'sms',
        'placeholder'   =>  '',
        'title'         => 'Схема проведения платежа',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            'sms'   => 'Одностадийная',
            'dms'   => 'Двухстадийная'
            )
        ),
    'allow_all'   => array(
        'value'         => '1',
        'placeholder'   =>  '',
        'title'         => 'Получение уведомлений',
        'description'   => '',
        'control_type'  => waHtmlControl::SELECT,
        'options'       => array(
            '1'   => 'Только серверов Mixplat',
            '0'   => 'Со всех ip'
            )
        ),

    'mixplat_ip_list'   => array(
        'value'         => "185.77.233.27\n185.77.233.29",
        'placeholder'   =>  '',
        'title'         => 'Список ip адресов Mixplat',
        'description'   => 'Каждый на новой строке',
        'control_type'  => waHtmlControl::TEXTAREA,
        ),

);
