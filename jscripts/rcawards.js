function awards_table(uid, pid, page)
{
	var awards_rows = 3;
	var awards_columns = 5;
	var n = awards_rows * awards_columns;
	var table;
	if (typeof awards[uid] !== "undefined")
	{
		var last_page = Math.floor(awards[uid].length / n);
		if (last_page > 0)
		{
			if (page == 0)
			{
				table = "<img src='images/dev/icons/previous_gray.png'></img>&nbsp;&nbsp;&nbsp;<a href='javascript:void()' onclick='awards_table("+uid+","+pid+","+(page+1)+");'><img src='images/dev/icons/next.png'></img></a><br>";
			} else if (page < last_page)
			{
				table = "<a href='javascript:void()' onclick='awards_table("+uid+","+pid+","+(page-1)+");'><img src='images/dev/icons/previous.png'></img></a>&nbsp;&nbsp;&nbsp;<a href='javascript:void()' onclick='awards_table("+uid+","+pid+","+(page+1)+");'><img src='images/dev/icons/next.png'></img></a><br>";
			} else
			{
				table = "<a href='javascript:void()' onclick='awards_table("+uid+","+pid+","+(page-1)+");'><img src='images/dev/icons/previous.png'></img></a>&nbsp;&nbsp;&nbsp;<img src='images/dev/icons/next_gray.png'></img><br>";
			}
		} else
		{
			table = "";
		}
		
		if (awards[uid].length > 0)
		{
			table += "<table style=\"width:100%;\">";
			
			if (page == last_page)
			{
				var dn = awards[uid].length % n;
				var drows = Math.floor(dn / awards_columns);
				var dcolumns = dn % awards_rows;
				var width = (100 / awards_columns) + "%";
				
				for (var i = 0; i <= drows; i++)
				{
					table += "<tr>";
					
					for (var j = 0; j < awards_columns; j++)
					{
						if (typeof awards[uid][(page*n)+(awards_columns*i)+j] !== "undefined")
						{
							table += "<td align=\"center\"><a title=\""+awards[uid][(page*n)+(awards_columns*i)+j][1]+": "+awards[uid][(page*n)+(awards_columns*i)+j][2]+"\" href=\"./misc.php?action=awardsgiven&aid="+awards[uid][(page*n)+(awards_columns*i)+j][3]+"\"><img src=\""+awards[uid][(page*n)+(awards_columns*i)+j][0]+"\"></a></td>";
						}
					}
					
					table += "</tr>";
				}
			}
			else
			{
				for (var i = 0; i < awards_rows; i++)
				{
					table += "<tr>";
					
					for (var j = 0; j < awards_columns; j++)
					{
						table += "<td align=\"center\"><a title=\""+awards[uid][(page*n)+(awards_columns*i)+j][1]+": "+awards[uid][(page*n)+(awards_columns*i)+j][2]+"\" href=\"./misc.php?action=awardsgiven&aid="+awards[uid][(page*n)+(awards_columns*i)+j][3]+"\"><img src=\""+awards[uid][(page*n)+(awards_columns*i)+j][0]+"\"></a></td>";
					}
					
					table += "</tr>";
				}
			}
			
			if (awards[uid].length > 1)
			{
				table += "</table><a href=\"./misc.php?action=userawards&uid="+uid+"\">"+awards[uid].length+" Prêmios</a>";
			}
			else
			{
				table += "</table><a href=\"./misc.php?action=userawards&uid="+uid+"\">"+awards[uid].length+" Prêmio</a>";
			}
		}
	
		jQuery("#awards_"+pid).html(table);
	}
}