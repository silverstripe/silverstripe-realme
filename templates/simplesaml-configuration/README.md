This directory is used to store the actual configuration files that SimpleSAMLphp uses, in order to ensure this 
content is available even between deployments. It is designed to be separate to the directory where certificates are 
stored, so that these can be independently controlled via normal directory permissions.

NOTE:
Content in this folder should never be directly edited, as it will be over-written the next time the `RealMeSetupTask` 
script is run. Instead, it is expected that developers will edit the templates that `RealMeSetupTask` uses, and then 
re-run the task.