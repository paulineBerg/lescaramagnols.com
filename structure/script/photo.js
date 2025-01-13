<script type="text/javascript">
		
			var MainPhotoID 	= "";
			var permutTab 		= new Array();
			var MaxNbIteration	= 7;
			var IsMoving		= false;
		
			function findPos(objId, dimType) {
			
				var curleft = curtop = 0;
				var obj = document.getElementById(objId);
				
				if (obj.offsetParent) {
					do {
						curleft += obj.offsetLeft;
						curtop += obj.offsetTop;
					} while (obj = obj.offsetParent);
				}
				
				if(dimType == 'top')
					return curtop;
				else
					return curleft;
			}
			
			
			function InitPhoto() {
			
				for(cpt = 1; cpt < 10; cpt++) {
			
				
					oImg = document.getElementById('photo' + cpt);
					iConID = 'cPhoto' + cpt;
				
					oImg.style.position = 'absolute';
					oImg.style.top 	= findPos(iConID, 'top') + 'px';
					oImg.style.left	= findPos(iConID, 'left') + 'px';
				}
				
				MainPhotoID = "photo1";
			}
			
			function permut(pId) {
			
				if(IsMoving == false) {

					IsMoving = true;
					
					
				
					permutTab['img1'] = new Array();
						permutTab['img1']['object'] 		= document.getElementById(pId);
						permutTab['img1']['destiTop']		= document.getElementById(MainPhotoID).offsetTop;
						permutTab['img1']['destiLeft']	= document.getElementById(MainPhotoID).offsetLeft;
						permutTab['img1']['destiWidth']	= document.getElementById(MainPhotoID).offsetWidth;
						permutTab['img1']['destiHeight']	= document.getElementById(MainPhotoID).offsetHeight;
					permutTab['img2'] = new Array();
						permutTab['img2']['object'] 		= document.getElementById(MainPhotoID);
						permutTab['img2']['destiTop']		= document.getElementById(pId).offsetTop;
						permutTab['img2']['destiLeft']	= document.getElementById(pId).offsetLeft;
						permutTab['img2']['destiWidth']	= document.getElementById(pId).offsetWidth;
						permutTab['img2']['destiHeight']	= document.getElementById(pId).offsetHeight;
					permutTab['NbIteration'] = MaxNbIteration;
					
					movePicture();
				}
			}
				
				
			function movePicture() {

				var oImg1 = permutTab['img1'];
				oImg1['object'].style.top 		= oImg1['object'].offsetTop - (getDimension(oImg1['destiTop'], oImg1['object'].offsetTop, permutTab['NbIteration']));									
				oImg1['object'].style.left 		= oImg1['object'].offsetLeft - (getDimension(oImg1['destiLeft'], oImg1['object'].offsetLeft, permutTab['NbIteration']));				
				oImg1['object'].style.width 	= oImg1['object'].offsetWidth - (getDimension(oImg1['destiWidth'], oImg1['object'].offsetWidth, permutTab['NbIteration']));				
				oImg1['object'].style.height 	= oImg1['object'].offsetHeight - (getDimension(oImg1['destiHeight'], oImg1['object'].offsetHeight, permutTab['NbIteration']));				

				var oImg2 = permutTab['img2'];
				oImg2['object'].style.top 		= oImg2['object'].offsetTop - (getDimension(oImg2['destiTop'], oImg2['object'].offsetTop, permutTab['NbIteration']));									
				oImg2['object'].style.left 		= oImg2['object'].offsetLeft - (getDimension(oImg2['destiLeft'], oImg2['object'].offsetLeft, permutTab['NbIteration']));				
				oImg2['object'].style.width 	= oImg2['object'].offsetWidth - (getDimension(oImg2['destiWidth'], oImg2['object'].offsetWidth, permutTab['NbIteration']));				
				oImg2['object'].style.height 	= oImg2['object'].offsetHeight - (getDimension(oImg2['destiHeight'], oImg2['object'].offsetHeight, permutTab['NbIteration']));
				
				permutTab['NbIteration']--;
				
				if(permutTab['NbIteration'] > 0) {
					window.setInterval('movePicture()', 10);
				} else {
					MainPhotoID 	= oImg1['object'].id;
					permutTab 		= new Array();
					IsMoving 		= false;
				}
			}
			
			function getDimension(borne1, borne2, nbDecoup) {
				return (borne2 - borne1) / nbDecoup;
			}
		
		</script>		
