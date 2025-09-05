<!-- // Update loadMessages function to include reactions
        // function loadMessages() {
        //     fetch('get_messages.php?room_id=<?php echo $roomId; ?>')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 const messagesContainer = document.getElementById('messages-container');
        //                 let messagesHTML = '';
        //                 const unreadMessageIds = [];
                        
        //                 data.messages.forEach(message => {
        //                     const isMyMessage = message.user_id === '<?php echo $userId; ?>';
        //                     const messageClass = isMyMessage ? 'message my-message' : 'message other-message';
        //                     const canDelete = isMyMessage || <?php echo $isAdmin ? 'true' : 'false'; ?>;
                            
        //                     // Check if message is read
        //                     const readBy = message.read_by || [];
        //                     const isRead = readBy.includes('<?php echo $userId; ?>');
                            
        //                     // If message is not from current user and not read yet, add to unread list
        //                     if (!isMyMessage && !isRead) {
        //                         unreadMessageIds.push(message.id);
        //                     }
                            
        //                     // Generate read receipt HTML
        //                     let readReceiptHTML = '';
        //                     if (isMyMessage && readBy.length > 1) { // More than 1 because sender is always in read_by
        //                         const readCount = readBy.length - 1; // Exclude sender
        //                         readReceiptHTML = `<div class="read-receipt">Read by ${readCount}</div>`;
        //                     }
                            
        //                     // File attachment HTML
        //                     let fileHTML = '';
        //                     if (message.has_file && message.file) {
        //                         const fileIcon = getFileIcon(message.file.type);
        //                         const fileSize = formatFileSize(message.file.size);
                                
        //                         fileHTML = `
        //                             <div class="file-attachment">
        //                                 <a href="${message.file.path}" target="_blank" download="${message.file.name}">
        //                                     <span class="file-icon">${fileIcon}</span>
        //                                     <span class="file-name">${message.file.name}</span>
        //                                     <span class="file-size">${fileSize}</span>
        //                                 </a>
        //                             </div>
        //                         `;
        //                     }
                            
        //                     // Generate reactions HTML
        //                     let reactionsHTML = '';
        //                     if (message.reactions && Object.keys(message.reactions).length > 0) {
        //                         reactionsHTML = '<div class="reactions-container">';
        //                         for (const [emoji, users] of Object.entries(message.reactions)) {
        //                             const count = users.length;
        //                             const hasReacted = users.includes('<?php echo $userId; ?>');
        //                             const badgeClass = hasReacted ? 'reaction-badge reacted' : 'reaction-badge';
        //                             reactionsHTML += `
        //                                 <div class="${badgeClass}" onclick="reactToMessage('${message.id}', '${emoji}')">
        //                                     <span class="reaction-emoji-small">${emoji}</span>
        //                                     <span class="reaction-count">${count}</span>
        //                                 </div>
        //                             `;
        //                         }
        //                         reactionsHTML += '</div>';
        //                     }
                            
        //                     // Generate reaction picker HTML
        //                     const reactionPickerHTML = `
        //                         <div class="reaction-picker" id="reaction-picker-${message.id}">
        //                             ${commonReactions.map(emoji => 
        //                                 `<span class="reaction-emoji" onclick="reactToMessage('${message.id}', '${emoji}')">${emoji}</span>`
        //                             ).join('')}
        //                         </div>
        //                     `;
                            
        //                     messagesHTML += `
        //                         <div class="${messageClass}" data-id="${message.id}">
        //                             <div class="message-info">${message.username} - ${message.time}</div>
        //                             <div class="message-bubble">
        //                                 ${message.text}
        //                                 ${fileHTML}
        //                                 ${reactionsHTML}
        //                                 <button class="reaction-btn" onclick="toggleReactionPicker('${message.id}')">😊</button>
        //                                 ${reactionPickerHTML}
        //                                 ${canDelete ? 
        //                                     `<div class="message-actions">
        //                                         <button class="delete-msg-btn" onclick="deleteMessage('${message.id}')">Delete</button>
        //                                     </div>` : ''
        //                                 }
        //                             </div>
        //                             ${readReceiptHTML}
        //                         </div>
        //                     `;
        //                 });
                        
        //                 messagesContainer.innerHTML = messagesHTML;
        //                 messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        
        //                 // Mark unread messages as read
        //                 if (unreadMessageIds.length > 0) {
        //                     markMessagesAsRead(unreadMessageIds);
        //                 }
        //             }
        //         })
        //         .catch(error => console.error('Error loading messages:', error));
        // } -->