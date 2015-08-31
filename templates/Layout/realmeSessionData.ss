<% if $RealMeSessionData %>
    <p>You're logged in as '$RealMeSessionData.UserFlt'. <a href="Security/logout">Logout.</a></p>
<% else %>
    <p>You're not currently logged in. <a href="Security/login">Login now.</a></p>
<% end_if %>
