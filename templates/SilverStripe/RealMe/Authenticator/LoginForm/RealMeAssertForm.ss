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

<div class="realme_widget realme_assert realme_theme_{$RealMeWidgetTheme} no_touch" style="z-index: 1;">
    <h2 class="realme_title">Prove your identity with RealMe</h2>
    <p class="realme_info">If you have verified your identity with RealMe you can securely prove who you are, right now.</p>

    <div class="realme_login_lockup">
        <form $FormAttributes>
            <% if $Actions %>
                <img src="$resourceURL('silverstripe/realme:client/dist/images/RealMe-logo@2x.png')" alt="RealMe" width="42" height="42">
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
        <a class="js_toggle_popup link whats_realme" href="https://www.realme.govt.nz" target="_blank" rel="noopener noreferrer">What’s RealMe?</a>

        <div class="realme_popup_wrapper realme_arrow_side_left">
            <!-- realme_popup -->
            <div class="realme_popup" style="width:420px;">
                <h2 class="realme_popup_title">The easy way to prove who you are online</h2>
                <p><strong>RealMe</strong> is a service from the New Zealand government that allows you to prove who you are online.</p>
                <h2 class="realme_popup_title">Have you already verified your identity with RealMe?</h2>
                <p>The answer is yes if you have applied online and then visited a PostShop to have your photo taken, or signed up for a verified identity when you renewed your passport online.</p>
                <h2 class="realme_popup_title">What's so good about being verified?</h2>
                <p>Once your identity is verified with <strong>RealMe</strong>, you'll be able to do lots of useful things online - like applying for a new bank account or getting a birth certificate - without needing to visit a branch. <a class="realme_find_out_more" target="_blank" rel="noopener noreferrer" href="https://www.realme.govt.nz/what-it-is/verify-your-identity/">Find out more<span class="realme_icon_find_out_more"></span></a></p>
                <p>You can also verify your address with NZ Post. <a class="realme_find_out_more" target="_blank" rel="noopener noreferrer" href="https://www.realme.govt.nz/what-it-is/verify-your-address/">Find out more<span class="realme_icon_find_out_more"></span></a></p>

                <span class="arrow">
                    <span class="front"></span>
                </span>
            </div><!-- /realme_popup -->
        </div>
        <!-- /realme_popup_wrapper -->
    </div>
</div>
