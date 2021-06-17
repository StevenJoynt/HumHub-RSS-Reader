# How to install the HumHub-RSS-Reader module

1. Point your web browser at the repository on GitHub
https://github.com/StevenJoynt/HumHub-RSS-Reader
2. Click the green **Code** button which is on the right, above the list of files
3. Choose **Download ZIP** and save the file to your computer
4. Log in to the web hosting account where your HumHub files are stored
**Note**: this is **NOT** your administrator login at your HubHub site, but your service provider login (maybe GoDaddy, or IONOS 1&1, etc.)
5. Navigate to the folder where the web site files are stored
It will probably be called something like your web site domain name, or it may be called **public_html** or **docroot**
Within this main folder, you should see sub-folders like: **assets**, **protected**, **static**, **themes**, **uploads**
6. Within the **protected** folder you'll find a **humhub** folder, and within there you'll find a **modules** folder
7. Upload the ZIP file you downloaded at step 3 into this folder, then unzip/unpack/extract it
This will populate a new folder called **HumHub-RSS-Reader-main** (it will not affect any other files)
8. If you are updating the module, and you already have a folder called **rss**, you will need to delete it
9. Rename the **HumHub-RSS-Reader-main** folder as **rss**

# How to enable the HumHub-RSS-Reader module

1. Log in to your HumHub web site as a site administrator
2. Click on your name/picture at the top right and select "Administration" from the menu
3. Select "Modules" from the "Administration menu"
4. Find the RSS module in the list and **enable** it

# How to configure the HumHub-RSS-Reader module

1. Navigate to an existing "HumHub Space" that you want to use this module in, or maybe create a new one
2. Click the **cog wheel** to the right, under the banner, to show the space configuration menu.
3. Select **Modules**
4. Find the RSS module in the list and **enable** it
5. Find the RSS module in the list and **configure** it
6. Enter the URL of the RSS Feed and press the **save** button
7. Wait !
The RSS feeds are processed as background jobs which happen once each hour.
The site administrator can find out when the next job will happen by navigating to "Administration... Information... Background Jobs"
eg. if it says "Last run (hourly): 46 minutes ago", you'll have to wait another 14 minutes before the RSS module is run.

