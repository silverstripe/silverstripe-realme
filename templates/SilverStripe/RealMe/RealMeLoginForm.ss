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
<% if $HasRealMeLastError %>
    <div class="message bad">$RealMeLastError</div>
<% end_if %>

<div class="realme_widget realme_primary_login realme_theme_{$RealMeWidgetTheme}">
    <h2 class="realme_title">Login with RealMe®</h2>

    <p class="realme_info">
        To access the $ServiceName1, you need a RealMe login. If you’ve used a RealMe login somewhere else, you can use
        it here too. If you don’t already have a username and password, just select Login and choose to create one.
    </p>

    <div class="realme_login_lockup">
        <form $FormAttributes>
            <% if $Actions %>
                <img src="$resourceURL('silverstripe/realme:client/images/RealMe-logo@2x.png')" alt="RealMe" width="42" height="42">
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
        <a class="js_toggle_popup whats_realme" href="https://www.realme.govt.nz" target="_blank" rel="noopener noreferrer">What’s RealMe?</a>
        <div class="realme_popup_wrapper realme_arrow_top_left">
            <!-- realme_popup -->
            <div class="realme_popup">
                <h2 class="realme_popup_title">To log in to $ServiceName2 you need a RealMe login.</h2>
                <p>$ServiceName3 uses RealMe login to secure and protect your personal information.</p>
                <p>
                    <strong>RealMe</strong> login is a service from the New Zealand government that includes a single
                    login, letting you use one username and password to access a wide range of services online.
                </p>
                <p>
                    Find out more at www.realme.govt.nz.
                </p>
                <span class="arrow">
                    <span class="front"></span>
                </span>
            </div><!-- /realme_popup -->
        </div>
        <!-- /realme_popup_wrapper -->
    </div>
</div>
