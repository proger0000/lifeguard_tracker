<?php
// Модальное окно "Сітка Постів" для директора (только просмотр)
?>
<div id="post-grid-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto" aria-labelledby="post-grid-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-2 sm:px-4 pt-4 pb-20 text-center sm:block sm:p-0">
         <div id="post-grid-modal-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
         <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
         <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle w-full max-w-7xl border border-gray-200">
              <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 z-10">
                  <h3 class="text-xl font-bold text-gray-900 flex items-center" id="post-grid-modal-title">
                      <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                          <i class="fas fa-th-large text-white text-sm"></i>
                      </div>
                      Сітка Постів за <span id="modal-selected-date" class="ml-2 font-mono bg-white px-2 py-1 rounded text-sm"><?php echo date("d.m.Y"); ?></span>
                 </h3>
                  <button type="button" 
                          class="text-gray-400 hover:text-red-600 transition-colors p-2 hover:bg-red-50 rounded-lg" 
                          onclick="closePostGridModal()">
                      <span class="sr-only">Закрити</span> 
                      <i class="fas fa-times text-xl"></i>
                  </button>
              </div>
             <div id="post-grid-modal-body" class="px-6 py-6 overflow-y-auto" style="max-height: calc(85vh - 80px);">
                   <div id="post-grid-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                   </div>
                  <div id="post-grid-loading" class="text-center py-16 text-gray-500 hidden">
                      <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                      <div>Завантаження...</div>
                  </div>
                  <div id="post-grid-nodata" class="text-center py-16 text-gray-500 hidden">
                      <i class="fas fa-info-circle text-2xl mb-3"></i>
                      <div>Немає даних для відображення за обрану дату.</div>
                  </div>
              </div>
         </div>
     </div>
</div> 