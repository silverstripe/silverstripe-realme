<%--"
# RealMe Secondary login module

RealMe secondary (webpage header) login component, to be placed in the top header on the right hand side. This is
intended for use in navigation bars, it does not login, only links to the primary login from on the
page: /Security/login

{@link realme/_config/config.yml}
{@link realme/code/RealMeLoginForm.php}

## CSS and JavaScript requirements

You should either:
- include the realme css and javascript into you site's theme
- or load them via Requirements in your base page controller, e.g. as RealMeLoginForm.php does

## Color scheme options

Color options not loaded from config.yml for this template, but these are the actual css classnames you can
apply to the .realme_widget element:

1. realme_theme_default
2. realme_theme_dark
3. realme_theme_light

## Popup window

The popup module is flexible, and can be configured by your developer to fit the available width in your page.
(popup not supported for IE6 or touch devices).

Select one of the four popup styles below and apply it to the .realme_popup_wrapper element:

1. realme_arrow_top_left
2. realme_arrow_top_right
3. realme_arrow_side_left
4. realme_arrow_side_right

You can specifiy the width of the popup by specifiying a width attribute for the .realme_popup element
or directly in your css, e.g. .realme_popup {width: 450px}

"--%>
<div class="realme_widget realme_secondary_login realme_theme_dark no_touch" style="z-index: 1;">
    <a href="{$BaseHref}Security/login#RealMeLoginForm_LoginForm" class="realme_login realme_pipe">Login <span class="realme_icon_link"></span></a>
    <a href="https://www.account.realme.govt.nz/account/" class="realme_create_account realme_pipe">Create <span class="realme_icon_link"></span></a>
    <div class="realme_popup_position">
        <a id="popup_trigger" href="http://www.realme.govt.nz" target="_blank" class="realme_link whats_realme">?</a>
        <div class="realme_popup_wrapper realme_arrow_top_left">
            <!-- realme_popup -->
            <div class="realme_popup">
                <h2 class="realme_popup_title">To login to this service you now need a RealMe account.</h2>
                <p><b>RealMe</b> is a service from the New Zealand government and New Zealand Post that includes a single login, letting you use one username and password to access a wide range of services online.</p>
                <p>But there is much more to <b>RealMe</b> than just the convenience of a single login.</p>
                <h2 class="realme_popup_title">Get Verified</h2>
                <p><b>RealMe</b> is also your secure online ID. Verify your <b>RealMe</b> account and use it to prove who you are online. This lets you to do lots of useful things over the internet that would normally require you to turn up in person. <a class="realme_find_out_more" target="_blank" href="http://www.realme.govt.nz">Find out more <span class="realme_icon_find_out_more"></span></a></p>
                <span class="arrow">
                    <span class="front"></span>
                </span>
            </div><!-- /realme_popup -->
        </div>
        <!-- /realme_popup_wrapper -->
    </div>
</div>
