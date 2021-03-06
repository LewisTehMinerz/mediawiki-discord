﻿<?php

final class MediawikiDiscord
{
	static function getUserText ($user)
	{
			global $wgServer, $wgScriptPath;

			$userUrl = $user->getUserPage()->getFullUrl();

			$userPageLink = MediawikiDiscordUtils::CreateMarkdownLink ($user, $userUrl);
			$userTalkLink = MediawikiDiscordUtils::CreateMarkdownLink (strtolower(MediawikiDiscord::translate("talk")), $user->getTalkPage()->getFullURL());
			$userContributionsLink = MediawikiDiscordUtils::CreateMarkdownLink (strtolower(MediawikiDiscord::translate("sp-deletedcontributions-contribs")), Title::newFromText("Special:Contributions/" . $user)->getFullURL());
			$userBlockLink = MediawikiDiscordUtils::CreateMarkdownLink (strtolower(MediawikiDiscord::translate("blocklink")), "<" . Title::newFromText("Special:Block/" . $user)->getFullURL() . ">"); // prevent embed - see #5

			return sprintf("%s (%s | %s | %s)", $userPageLink, $userTalkLink, $userContributionsLink, $userBlockLink);
	}

	static function getPageText ($wikiPage, $links = true)
	{
			$pageUrl = $wikiPage->getTitle()->getFullURL();

			$pageLink = MediawikiDiscordUtils::CreateMarkdownLink ($wikiPage->getTitle()->getFullText(), $pageUrl);

			if ($links == true)
			{
					$revisionId = $wikiPage->getRevision()->getID();

					$editLink = MediawikiDiscordUtils::CreateMarkdownLink (MediawikiDiscord::translate("tags-edit"), $wikiPage->getTitle()->getFullUrl("action=edit"));
					$historyLink = MediawikiDiscordUtils::CreateMarkdownLink (MediawikiDiscord::translate("hist"), $wikiPage->getTitle()->getFullUrl("action=history"));
					
					// need to use arrays here for the second parameter since mediawiki doesn't allow more than two query string parameters, but you can use arrays to specify more.
					$diffLink = MediawikiDiscordUtils::CreateMarkdownLink (MediawikiDiscord::translate("diff"), $wikiPage->getTitle()->getFullUrl("diff=prev", array("oldid" => $revisionId)));
					$undoLink = MediawikiDiscordUtils::CreateMarkdownLink (MediawikiDiscord::translate("editundo"), $wikiPage->getTitle()->getFullUrl("action=edit", array("undoafter" => (int)($revisionId - 1), "undo" => $revisionId)));

					return sprintf("%s (%s | %s | %s | %s)", $pageLink, $editLink, $historyLink, $diffLink, $undoLink);
			}
			else
			{
					return $pageLink;
			}
	}
	
	static function getTitleText ($title)
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($title, $title->getFullURL());
	}
	
	static function getFileText ($file)
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($file->getName(), $file->getTitle()->getFullUrl());
	}
	
	static function translate ($key, ...$parameters) 
	{
		global $wgDiscordNotificationsLanguage;
		
		if ($wgDiscordNotificationsLanguage != null) 
		{
			return wfMessage($key, $parameters)->inLanguage($wgDiscordNotificationsLanguage)->plain();
		} 
		else 
		{
			return wfMessage($key, $parameters)->inContentLanguage()->plain();
		}
	}
	
	static function isNotificationExcluded ($hook) 
	{
		global $wgDiscordExcludedNotifications;
	
		return in_array($hook, $wgDiscordExcludedNotifications);
	}
}

final class MediawikiDiscordHooks 
{	
	static function onPageContentSaveComplete ($wikiPage, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId)
	{		
		if (MediawikiDiscord::isNotificationExcluded("onPageContentSaveComplete")) 
		{
			return;
		}
		
		if ($status->value['new'] == true) //page is just created, there is no need to trigger second notification
		{
			return;
		}
					
		if ($isMinor) 
		{
			$messageTranslationKey = "onPageContentSaveComplete_MinorEdit";
		}
		else
		{
			$messageTranslationKey = "onPageContentSaveComplete";
		}
							
		$message = MediawikiDiscord::translate($messageTranslationKey, MediawikiDiscord::getUserText($user), 
																	   MediawikiDiscord::getPageText($wikiPage));
							
		if (empty($summary) == false)
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('summary'), 
						$summary);
		}
		
	    (new DiscordNotification($message))->Send();		
	}
	
	static function onPageContentInsertComplete ($wikiPage, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision) 
	{
		if (MediawikiDiscord::isNotificationExcluded("onPageContentInsertComplete")) 
		{
			return;
		}
		
		if ($wikiPage->getTitle()->getNamespace() == NS_FILE) //the page is file, there is no need to trigger second notification of file's page creation
		{
			return;
		}

		$message = MediawikiDiscord::translate('onPageContentInsertComplete', MediawikiDiscord::getUserText($user), 
																			  MediawikiDiscord::getPageText($wikiPage, false));
																			  
		if (empty($summary) == false)
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('summary'), 
						$summary);
		}
																			  
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onTitleMoveComplete ($title, $newTitle, $user, $oldid, $newid, $reason, $revision) 
	{		
		if (MediawikiDiscord::isNotificationExcluded("onTitleMoveComplete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onTitleMoveComplete', MediawikiDiscord::getUserText($user), 
																	  MediawikiDiscord::getTitleText($title),
																	  MediawikiDiscord::getTitleText($newTitle));
																	  
		if (empty($reason) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						wfMessage('mergehistory-reason')->inContentLanguage()->plain(), 
						$reason);
		}
		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onArticleDeleteComplete($wikiPage, $user, $reason)
	{
		if (MediawikiDiscord::isNotificationExcluded("onArticleDeleteComplete")) 
		{
			return;
		}
		
		if ($wikiPage->getTitle()->getNamespace() == NS_FILE) //the page is file, there is no need to trigger second notification of file's page deletion
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onArticleDeleteComplete', MediawikiDiscord::getUserText($user), 
																		  MediawikiDiscord::getPageText($wikiPage, false));
																		  
		if (empty($reason) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('mergehistory-reason'), 
						$reason);
		}
		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onArticleUndelete($title, $create, $comment)
	{
		if (MediawikiDiscord::isNotificationExcluded("onArticleUndelete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onArticleUndelete', MediawikiDiscord::getTitleText($title));	
														
		if (empty($comment) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('import-comment'), 
						$comment);
		}
		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onArticleProtectComplete ($wikiPage, $user, $protect, $reason, $moveonly) 
	{
		if (MediawikiDiscord::isNotificationExcluded("onArticleProtectComplete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onArticleProtectComplete', MediawikiDiscord::getUserText($user), 
																		   MediawikiDiscord::getPageText($wikiPage, false));
																		   
		if (empty($reason) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('mergehistory-reason'), 
						$reason);
		}
		
	    (new DiscordNotification($message))->Send();	
	}	
	
	static function onUploadComplete($image) 
	{ 
		if (MediawikiDiscord::isNotificationExcluded("onUploadComplete")) 
		{
			return;
		}
		
	    global $wgUser;
		
		$isNewRevision = count($image->getLocalFile()->getHistory()) > 0;
						
		if ($isNewRevision == true) 
		{
			$message = MediawikiDiscord::translate('onUploadComplete_NewVersion', MediawikiDiscord::getUserText($wgUser),
																				  MediawikiDiscord::getFileText($image->getLocalFile()));
		}	
		else
		{
			$message = MediawikiDiscord::translate('onUploadComplete', MediawikiDiscord::getUserText($wgUser),
																	   MediawikiDiscord::getFileText($image->getLocalFile()));
		}	
		
		$discordNotification = new DiscordNotification($message);
		
		$mimeType = $image->getLocalFile()->getMimeType();
		
		if (($mimeType == "image/jpeg") 
		||  ($mimeType == "image/png")
		||  ($mimeType == "image/gif")
		||  ($mimeType == "image/webp"))
		{				
			$imageUrl = MediawikiDiscordUtils::RemoveMultipleSlashes($image->getLocalFile()->getFullUrl());
			
			$discordNotification->SetEmbedImage($imageUrl);
		}
			
		$discordNotification->Send(); 
	}
	
	static function onFileDeleteComplete($file, $oldimage, $article, $user, $reason)
	{
		if (MediawikiDiscord::isNotificationExcluded("onFileDeleteComplete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onFileDeleteComplete', MediawikiDiscord::getUserText($user), 
																	   MediawikiDiscord::getFileText($file));
																	   
		if (empty($reason) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('mergehistory-reason'), 
						$reason);
		}
		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onLocalUserCreated($user, $autocreated) 
	{ 
		if (MediawikiDiscord::isNotificationExcluded("onLocalUserCreated")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onLocalUserCreated', MediawikiDiscord::getUserText($user));
													 
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onBlockIpComplete($block, $user)
	{
		if (MediawikiDiscord::isNotificationExcluded("onBlockIpComplete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onBlockIpComplete', MediawikiDiscord::getUserText($user), 
																	MediawikiDiscord::getUserText($block->getTarget()));
																	
		if (empty($block->mReason) == false) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('mergehistory-reason'), 
						$block->mReason);
		}
			
		if (($expires = strtotime($block->mExpiry))) 
		{
			$message .= sprintf(" (%s `%s`)", 
						MediawikiDiscord::translate('blocklist-expiry'), 
						date('Y-m-d H:i:s', $expires));
		} 
		else 
		{
			if ($block->mExpiry == "infinity") 
			{
				$message .= sprintf(" (`%s`)", 
							MediawikiDiscord::translate('infiniteblock'));	
			}
			else
			{
				$message .= sprintf(" (%s `%s`)", 
							MediawikiDiscord::translate('blocklist-expiry'), 
							$block->mExpiry );
			}			
		}
		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onUnblockUserComplete($block, $user)
	{
		if (MediawikiDiscord::isNotificationExcluded("onUnblockUserComplete")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onUnblockUserComplete', MediawikiDiscord::getUserText($user), 
																		MediawikiDiscord::getUserText($block->getTarget()));
																		
	    (new DiscordNotification($message))->Send();	
	}
	
	static function onUserRights($user, array $addedGroups, array $removedGroups)
	{
		if (MediawikiDiscord::isNotificationExcluded("onUserRights")) 
		{
			return;
		}
		
		$message = MediawikiDiscord::translate('onUserRights', MediawikiDiscord::getUserText($user));
		
		if (count($addedGroups) > 0) 
		{
			$message .= sprintf(" %s: `%s`", 
						MediawikiDiscord::translate('added'), 
						join(', ', $addedGroups));
		}		
		
		if (count($removedGroups) > 0) 
		{
			$message .= sprintf(" %s: `%s`", 
						MediawikiDiscord::translate('removed'), 
						join(', ', $removedGroups));
		}	
	
	    (new DiscordNotification($message))->Send();	
	}
}

final class DiscordNotification
{
	private $message;
	private $embedImageUrl;
	
	public function __construct($message) 
	{
        $this->message = $message;
    }
	
	public function SetMessage ($message) 
	{
		$this->message = $message;
	}
	
	public function SetEmbedImage ($embedImageUrl)
	{
		$this->embedImageUrl = $embedImageUrl;
	}
	
	public function Send ()
	{
		global $wgDiscordWebhookUrl;
		global $wgSitename;
		
		$userName = $wgSitename;
						
		if (strlen($userName) >= 32) //32 characters is a limit of Discord usernames
		{
			$userName = substr($userName, 0, -(strlen($userName) - 32)); //if the wiki's name is too long, just remove last characters
		}
		
		$json = new stdClass();
		$json->content = $this->message;	
		$json->username = $userName;
		
		if ($this->embedImageUrl != null)
		{
			$json->embeds[0]->image->url = $this->embedImageUrl;
		}
		
		$data = array
		(
			'http' => array
			(				
				'method'  => 'POST',
				'content' => json_encode($json)
			)
		);

		file_get_contents($wgDiscordWebhookUrl, false, stream_context_create($data));	
	}
}

?>
