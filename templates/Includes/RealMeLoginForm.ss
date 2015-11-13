<%--"

# RealMe Primary Login Module

The RealMe primary (webpage body) login box, is available in two sizes, recommended on the right hand side of the page
near the top.

{@link realme/_config/config.yml}
{@link realme/code/RealMeLoginForm.php}


## Color scheme options

Color options are defined in config.yml, but these are the actual css classnames you can choose from:

1. realme_theme_default
2. realme_theme_dark
3. realme_theme_light


## Popup window

The popup module is flexible, and can be configured by your developer to fit the available width in your page.
(popup not supported for IE6 or touch devices).

Select one of the four popup styles below and apply it to the .realme_popup_wrapper element

1. realme_arrow_top_left
2. realme_arrow_top_right
3. realme_arrow_side_left
4. realme_arrow_side_right

You can specify the width of the popup by specifying a width attribute for the .realme_popup element
or directly in your css, e.g. .realme_popup {width: 450px}

"--%>
<div class="realme_widget realme_primary_login realme_theme_{$RealMeWidgetTheme}">
    <h2 class="realme_title">Login with RealMe®</h2>
    <p class="realme_info">To access the [online service], you need a RealMe login. If you’ve created a RealMe login
    somewhere else, you can use it here too.</p>
    <div class="realme_login_lockup">
        <form $FormAttributes>
            <% if $Actions %>
                <img src="{$BaseHref}realme/images/RealMe-logo@2x.png" alt="RealMe" width="42" height="42">
                <div class="realme_btn_margin">
                <% loop $Actions %>
                    $Field
                <% end_loop %>
                </div>
            <% end_if %>
            <% loop $Fields %>
                $FieldHolder
            <% end_loop %>
        </form>
    </div>
    <div class="realme_popup_position">
        <a class="js_toggle_popup whats_realme" href="http://www.realme.govt.nz">What’s RealMe?</a>
        <div class="realme_popup_wrapper realme_arrow_top_left">
            <!-- realme_popup -->
            <div class="realme_popup">
                <h2 class="realme_popup_title">To login to this service you now need a RealMe login.</h2>
                <p><b>RealMe</b> is a service from the New Zealand government and New Zealand Post that includes a
                single login, letting you use one username and password to access a wide range of services online.</p>
                <p>But there is much more to <b>RealMe</b> than just the convenience of a single login.</p>
                <h2 class="realme_popup_title">Get Verified</h2>
                <p><b>RealMe</b> is also your secure online ID. Verify your <b>RealMe</b> login and use it to prove who
                you are online. This lets you to do lots of useful things over the internet that would normally require
                 you to turn up in person. <a class="realme_find_out_more" target="_blank"
                 href="http://www.realme.govt.nz">Find out more <span class="realme_icon_find_out_more"></span></a>
                </p>
                <span class="arrow">
                    <span class="front"></span>
                </span>
            </div><!-- /realme_popup -->
        </div>
        <!-- /realme_popup_wrapper -->
    </div>
    <a class="realme_create_account" href="https://www.account.realme.govt.nz/account/" target="_blank">Create a RealMe login <span class="realme_icon_link"></span></a>
</div>